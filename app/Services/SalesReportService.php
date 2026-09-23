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

    $totalSales = (float) $payments->sum('amount');
    $totalCost = (float) $payments->sum(fn($p) => $p->order->total_cost);
    $totalProfit = (float) $payments->sum(fn($p) => $p->order->total - $p->order->total_cost);
    $totalWasteCost = $this->totalWasteCost($from, $to);

    return [
      'total_sales'             => $totalSales,
      'total_cash'              => (float) $payments->where('method', 'cash')->sum('amount'),
      'total_transfer'          => (float) $payments->where('method', 'transfer')->sum('amount'),
      'total_cost'              => $totalCost,
      'total_profit'            => $totalProfit,
      'total_waste_cost'        => $totalWasteCost,
      'net_profit_after_waste'  => $totalProfit - $totalWasteCost,
      'orders_count'            => $payments->count(),
    ];
  }

  // Suma el costo perdido en mermas dentro del rango — se resta de la ganancia
  // para que el cierre de caja refleje la pérdida real, no solo lo vendido.
  public function totalWasteCost(Carbon $from, Carbon $to): float
  {
    return (float) \App\Models\ProductWaste::query()
      ->whereBetween('wasted_at', [$from, $to])
      ->sum('total_cost');
  }

  public function productBreakdown(Carbon $from, Carbon $to)
  {
    return $this->productBreakdownQuery($from, $to)
      ->paginate(10)
      ->withQueryString();
  }

  public function productBreakdownAll(Carbon $from, Carbon $to)
  {
    return $this->productBreakdownQuery($from, $to)->get();
  }

  // Cantidad vendida por producto en el rango, solo de pedidos con pago vigente (no reversado).
  // Se consulta directo a nivel de SQL (no via Eloquent) porque agrupar/sumar miles de filas
  // es mucho más liviano así que cargando todos los order_items en memoria.
  public function productBreakdownQuery(Carbon $from, Carbon $to)
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
      ->orderByDesc('quantity_sold');
  }
}
