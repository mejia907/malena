<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Traits\StockReservable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use StockReservable;

    // Agrega un producto al pedido de la mesa y reserva (descuenta) el stock en ese momento.
    // Si no hay pedido abierto, lo crea.
    public function addItem(Request $request, Table $table): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($table, $data) {
            $order = $table->activeOrder()->first();

            if ($order) {
                // Bloquea el pedido mientras se procesa, para serializar taps rápidos consecutivos
                $order = Order::query()->lockForUpdate()->find($order->id);

                // Caja pudo cobrarlo justo antes de este toque
                $this->assertOrderIsOpen($order);
            } else {
                $order = $table->orders()->create([
                    'status'    => OrderStatus::Open->value,
                    'opened_at' => now(),
                ]);
            }

            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            // Con reserva al agregar, products.stock YA descuenta lo que hay en pedidos abiertos
            // (incluido este). Por eso se compara solo lo que se pide ahora contra lo que queda
            // disponible; sumar lo que ya está en el pedido lo contaría dos veces.
            if ($data['quantity'] > $product->stock) {
                throw ValidationException::withMessages([
                    'stock' => $product->stock > 0
                        ? "Solo quedan {$product->stock} unidades disponibles de \"{$product->name}\"."
                        : "No hay más stock disponible de \"{$product->name}\".",
                ]);
            }

            $item = $order->items()->where('product_id', $product->id)->first();

            if ($item) {
                $newQuantity = $item->quantity + $data['quantity'];

                $item->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $newQuantity * $item->unit_price,
                ]);
            } else {
                $item = $order->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $data['quantity'],
                    // Snapshot de precios: no dependen de futuros cambios en el producto
                    'unit_price' => $product->sale_price,
                    'unit_cost'  => $product->cost_price,
                    'subtotal'   => $product->sale_price * $data['quantity'],
                ]);
            }

            // Reserva (descuenta) del stock lo que se acaba de agregar
            $this->syncOrderItemStock($item, $data['quantity']);

            $order->recalculateTotals();

            if ($table->status !== TableStatus::Occupied) {
                $table->update(['status' => TableStatus::Occupied->value]);
            }
        });

        return back();
    }

    // Cambia la cantidad de un item escribiéndola a mano (0 = eliminarlo y devolver el stock)
    public function updateItemQuantity(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $itemRow = OrderItem::query()->find($item);

            if (! $itemRow) {
                return; // ya lo eliminó otro dispositivo; el polling refresca la vista
            }

            $order = Order::query()->lockForUpdate()->findOrFail($itemRow->order_id);
            $this->assertOrderIsOpen($order);

            $itemRow = OrderItem::query()->lockForUpdate()->find($item);

            if (! $itemRow) {
                return;
            }

            $product = Product::query()->lockForUpdate()->find($itemRow->product_id);

            if ($data['quantity'] === 0) {
                // Antes se borraba sin devolver las unidades reservadas
                if ($product) {
                    $this->reserveStock($product, $itemRow->quantity, 'release');
                }

                $itemRow->delete();
                $order->recalculateTotals();

                return;
            }

            // Delta = nueva cantidad - actual (positivo = reservar más, negativo = devolver)
            $delta = $data['quantity'] - $itemRow->quantity;

            // Solo las unidades EXTRA compiten contra el stock disponible
            if ($delta > $product->stock) {
                throw ValidationException::withMessages([
                    'stock' => $product->stock > 0
                        ? "Solo quedan {$product->stock} unidades más disponibles de \"{$product->name}\"."
                        : "No hay más stock disponible de \"{$product->name}\".",
                ]);
            }

            if ($delta !== 0) {
                $this->syncOrderItemStock($itemRow, $delta);
            }

            $itemRow->update([
                'quantity' => $data['quantity'],
                'subtotal' => $data['quantity'] * $itemRow->unit_price,
            ]);

            $order->recalculateTotals();
        });

        return back();
    }

    // Resta 1 unidad de un item (si llega a 0, lo elimina) y devuelve esa unidad al stock.
    // Opera sobre la cantidad actual en la base de datos, no sobre la que ve el celular,
    // así dos toques seguidos o con WiFi lento nunca restan de más.
    public function decrementItem(int $item): RedirectResponse
    {
        DB::transaction(function () use ($item) {
            $itemRow = OrderItem::query()->find($item);

            if (! $itemRow) {
                return; // ya fue eliminado desde otro dispositivo
            }

            $order = Order::query()->lockForUpdate()->findOrFail($itemRow->order_id);
            $this->assertOrderIsOpen($order);

            $itemRow = OrderItem::query()->lockForUpdate()->find($item);

            if (! $itemRow) {
                return;
            }

            if ($itemRow->quantity <= 1) {
                // Última unidad: se quita la línea y se devuelve esa unidad
                $product = Product::query()->lockForUpdate()->find($itemRow->product_id);

                if ($product) {
                    $this->reserveStock($product, $itemRow->quantity, 'release');
                }

                $itemRow->delete();
            } else {
                // Mismo mecanismo que updateItemQuantity cuando la diferencia es negativa
                $this->syncOrderItemStock($itemRow, -1);

                $newQuantity = $itemRow->quantity - 1;

                $itemRow->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $newQuantity * $itemRow->unit_price,
                ]);
            }

            $order->recalculateTotals();
        });

        return back();
    }

    // Quita un item completo del pedido y devuelve todas sus unidades al stock
    public function removeItem(int $item): RedirectResponse
    {
        DB::transaction(function () use ($item) {
            $itemRow = OrderItem::query()->find($item);

            if (! $itemRow) {
                return;
            }

            $order = Order::query()->lockForUpdate()->findOrFail($itemRow->order_id);
            $this->assertOrderIsOpen($order);

            $itemRow = OrderItem::query()->lockForUpdate()->find($item);

            if (! $itemRow) {
                return;
            }

            $product = Product::query()->lockForUpdate()->find($itemRow->product_id);

            if ($product) {
                $this->reserveStock($product, $itemRow->quantity, 'release');
            }

            $itemRow->delete();
            $order->recalculateTotals();
        });

        return back();
    }

    // Traslada un pedido abierto a otra mesa libre (el stock ya está reservado, no se toca)
    public function moveTable(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Open) {
            return back()->with('error', 'Solo se puede trasladar un pedido abierto.');
        }

        $data = $request->validate([
            'table_id' => [
                'required',
                Rule::exists('tables', 'id')->where('status', TableStatus::Free->value),
            ],
        ]);

        DB::transaction(function () use ($order, $data) {
            $previousTable = $order->table;
            $newTable = Table::findOrFail($data['table_id']);

            $order->update(['table_id' => $newTable->id]);
            $newTable->update(['status' => TableStatus::Occupied->value]);
            $previousTable->update(['status' => TableStatus::Free->value]);
        });

        return redirect()->route('tables.show', $data['table_id'])
            ->with('success', 'Pedido movido a la nueva mesa.');
    }

    // Cancela un pedido sin pagar: devuelve al stock todo lo que tenía reservado
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Open) {
            return back()->with('error', 'Solo se puede cancelar un pedido abierto (sin pagar).');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($order, $data) {
            // Bloqueo y revisión dentro de la transacción: si el botón se toca dos veces
            // (o caja cobra al mismo tiempo), la segunda llamada no devuelve el stock otra vez
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Open) {
                return;
            }

            foreach ($order->items as $item) {
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product) {
                    $this->reserveStock($product, $item->quantity, 'release');
                }
            }

            $order->update([
                'status'        => OrderStatus::Cancelled->value,
                'cancel_reason' => $data['reason'] ?? null,
                'closed_at'     => now(),
            ]);

            $order->table->update(['status' => TableStatus::Free->value]);
        });

        return redirect()->route('tables.index')->with('info', 'Pedido cancelado. La mesa quedó libre.');
    }

    // Asigna o actualiza el nombre del cliente en el pedido activo de la mesa
    public function updateCustomerName(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
        ]);

        $order->update(['customer_name' => $data['customer_name'] ?: null]);

        return back();
    }

    // Evita modificar un pedido que ya se cobró o canceló desde otro dispositivo
    // (celular con la pantalla desactualizada): alteraría el historial y devolvería stock de más
    private function assertOrderIsOpen(Order $order): void
    {
        if ($order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido ya no está abierto, no se puede modificar.',
            ]);
        }
    }
}
