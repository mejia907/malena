<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
  public function summarize(Carbon $from, Carbon $to): array
  {
    $payments = Payment::query()
      ->with('order.items')
      ->whereNull('reversed_at')
      ->whereBetween('paid_at', [$from, $to])
      ->get();

    return [
      'total_sales'    => (float) $payments->sum('amount'),
      'total_cash'     => (float) $payments->where('method', 'cash')->sum('amount'),
      'total_transfer' => (float) $payments->where('method', 'transfer')->sum('amount'),
      'total_cost'     => (float) $payments->sum(fn($p) => $p->order->total_cost),
      'total_profit'   => (float) $payments->sum(fn($p) => $p->order->total - $p->order->total_cost),
      'orders_count'   => $payments->count(),
    ];
  }

  // Cantidad vendida por producto en el rango, solo de pedidos con pago vigente (no reversado).
  // Se consulta directo a nivel de SQL (no via Eloquent) porque agrupar/sumar miles de filas
  // es mucho más liviano así que cargando todos los order_items en memoria.
  public function productBreakdown(Carbon $from, Carbon $to)
  {
    return DB::table('order_items')
      ->join('orders', 'orders.id', '=', 'order_items.order_id')
      ->join('payments', 'payments.order_id', '=', 'orders.id')
      ->join('products', 'products.id', '=', 'order_items.product_id')
      ->whereNull('payments.reversed_at')
      ->whereBetween('payments.paid_at', [$from, $to])
      ->groupBy('products.id', 'products.name')
      ->select(
        'products.id',
        'products.name',
        DB::raw('SUM(order_items.quantity) as quantity_sold'),
        DB::raw('SUM(order_items.subtotal) as total_sold'),
      )
      ->orderByDesc('quantity_sold')
      ->paginate(10)
      ->withQueryString();
  }
}
