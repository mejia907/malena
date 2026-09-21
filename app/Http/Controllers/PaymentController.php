<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    // Registra el pago, descuenta stock y libera la mesa
    public function store(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Open) {
            return back()->withErrors(['order' => 'Este pedido ya fue pagado o cancelado.']);
        }

        $data = $request->validate([
            'method' => ['required', 'in:cash,transfer'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->loadMissing('items.product');

            // Valida stock ANTES de descontar. Evita stock negativo si dos meseros
            // pagan pedidos casi simultáneos con el mismo producto agotándose.
            foreach ($order->items as $item) {
                $product = $item->product()->lockForUpdate()->first(); // bloqueo de fila para evitar condición de carrera

                if ($product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuficiente de \"{$product->name}\" (disponible: {$product->stock}).",
                    ]);
                }
            }

            foreach ($order->items as $item) {
                $item->product()->decrement('stock', $item->quantity);
            }

            $order->update([
                'status'    => OrderStatus::Closed,
                'closed_at' => now(),
            ]);

            $order->table->update(['status' => TableStatus::Free->value]);

            Payment::create([
                'order_id' => $order->id,
                'method'   => $data['method'],
                'amount'   => $order->total,
                'paid_at'  => now(),
            ]);
        });

        return redirect()->route('tables.index');
    }

    // Anula una venta YA pagada: devuelve el stock y marca el pago como reversado
    // (no se borra nada, para no perder trazabilidad contable)
    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        if (! $payment->is_valid) {
            return back()->withErrors(['payment' => 'Este pago ya fue anulado anteriormente.']);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($payment, $data) {
            $order = $payment->order()->with('items')->first();

            foreach ($order->items as $item) {
                $item->product()->increment('stock', $item->quantity);
            }

            $payment->update([
                'reversed_at'     => now(),
                'reversed_reason' => $data['reason'],
            ]);

            $order->update(['status' => OrderStatus::Cancelled]);

            // Solo libera la mesa si no tiene otro pedido abierto encima
            // (pudo haberse ocupado de nuevo desde que se pagó esta venta)
            if (! $order->table->activeOrder()->exists()) {
                $order->table->update(['status' => TableStatus::Free]);
            }
        });

        return back();
    }
}