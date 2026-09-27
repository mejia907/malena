<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    // Agrega un producto al pedido de la mesa. Si no hay pedido abierto, lo crea.
    public function addItem(Request $request, Table $table): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($table, $data) {
            $order = $table->activeOrder()->first() ?? $table->orders()->create([
                'status'    => OrderStatus::Open,
                'opened_at' => now(),
            ]);

            // Bloquea la fila del producto mientras validamos y escribimos, para que dos
            // dispositivos agregando el mismo producto al mismo tiempo no superen el stock real
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            $item = $order->items()->where('product_id', $product->id)->first();
            $currentQuantityInOrder = $item?->quantity ?? 0;
            $desiredQuantity = $currentQuantityInOrder + $data['quantity'];

            if ($desiredQuantity > $product->stock) {
                $available = $product->stock - $currentQuantityInOrder;
                throw ValidationException::withMessages([
                    'stock' => $available > 0
                        ? "Solo quedan {$available} unidades disponibles de \"{$product->name}\"."
                        : "No hay más stock disponible de \"{$product->name}\".",
                ]);
            }

            if ($item) {
                $item->update([
                    'quantity' => $desiredQuantity,
                    'subtotal' => $desiredQuantity * $item->unit_price,
                ]);
            } else {
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $data['quantity'],
                    // Snapshot de precios: no dependen de futuros cambios en el producto
                    'unit_price' => $product->sale_price,
                    'unit_cost'  => $product->cost_price,
                    'subtotal'   => $product->sale_price * $data['quantity'],
                ]);
            }

            $order->recalculateTotals();

            if ($table->status !== TableStatus::Occupied) {
                $table->update(['status' => TableStatus::Occupied->value]);
            }
        });

        return back();
    }

    // Actualiza la cantidad de un item existente (0 = eliminarlo)
    public function updateItemQuantity(Request $request, OrderItem $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($item, $data) {
            $order = $item->order;

            if ($data['quantity'] === 0) {
                $item->delete();
                $order->recalculateTotals();
                return;
            }

            // Bloquea el producto para validar contra el stock real en este momento
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($data['quantity'] > $product->stock) {
                throw ValidationException::withMessages([
                    'stock' => "Solo hay {$product->stock} unidades disponibles de \"{$product->name}\".",
                ]);
            }

            $item->update([
                'quantity' => $data['quantity'],
                'subtotal' => $data['quantity'] * $item->unit_price,
            ]);

            $order->recalculateTotals();
        });

        return back();
    }

    // Elimina un item del pedido
    public function removeItem(OrderItem $item): RedirectResponse
    {
        DB::transaction(function () use ($item) {
            $order = $item->order;
            $item->delete();
            $order->recalculateTotals();
        });

        return back();
    }

    // Traslada un pedido abierto a otra mesa libre (caso de borde: cliente se cambia de mesa)
    public function moveTable(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Open) {
            return back()->withErrors(['order' => 'Solo se puede trasladar un pedido abierto.']);
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

        return redirect()->route('tables.show', $data['table_id']);
    }

    // Cancela un pedido SIN pagar (no hay stock que devolver: nunca se descontó)
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Open) {
            return back()->withErrors(['order' => 'Solo se puede cancelar un pedido abierto (sin pagar).']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->update([
                'status'        => OrderStatus::Cancelled->value,
                'cancel_reason' => $data['reason'] ?? null,
                'closed_at'     => now(),
            ]);

            $order->table->update(['status' => TableStatus::Free->value]);
        });

        return redirect()->route('tables.index');
    }
}