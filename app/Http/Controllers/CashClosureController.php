<?php

namespace App\Http\Controllers;

use App\Models\CashClosure;
use App\Models\Payment;
use App\Models\ProductWaste;
use App\Services\SalesReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CashClosureController extends Controller
{
    public function __construct(private SalesReportService $reportService) {}

    public function pending()
    {
        $periodStart = $this->resolvePeriodStart();
        $periodEnd = now();
        $summary = $this->reportService->summarize($periodStart, now());

        return Inertia::render('CashClosures/Pending', [
            'summary' => array_merge($summary, ['period_start' => $periodStart]),
            'history' => CashClosure::query()
                ->latest('period_end')
                ->paginate(10)
                ->withQueryString(),
            // Detalle para los modales: qué se vendió y qué se perdió en el periodo pendiente
            'soldProducts' => $this->reportService->productBreakdownAll($periodStart, $periodEnd),
            'wastes'       => ProductWaste::query()
                ->with('product:id,name')
                ->whereBetween('wasted_at', [$periodStart, $periodEnd])
                ->orderByDesc('wasted_at')
                ->get()
                ->map(fn(ProductWaste $waste) => [
                    'id'         => $waste->id,
                    'product'    => $waste->product->name,
                    'quantity'   => $waste->quantity,
                    'reason'     => $waste->reason,
                    'note'       => $waste->note,
                    'total_cost' => $waste->total_cost,
                    'wasted_at'  => $waste->wasted_at,
                ]),
        ]);
    }

    public function store(): RedirectResponse
    {
        DB::transaction(function () {
            $periodStart = $this->resolvePeriodStart();
            $periodEnd = now();
            $summary = $this->reportService->summarize($periodStart, $periodEnd);

            CashClosure::create([
                'period_start'            => $periodStart,
                'period_end'              => $periodEnd,
                'total_sales'             => $summary['total_sales'],
                'total_cash'              => $summary['total_cash'],
                'total_transfer'          => $summary['total_transfer'],
                'total_cost'              => $summary['total_cost'],
                'total_profit'            => $summary['total_profit'],
                'total_waste_cost'        => $summary['total_waste_cost'],
                'net_profit_after_waste'  => $summary['net_profit_after_waste'],
                'orders_count'            => $summary['orders_count'],
            ]);
        });

        return redirect()->route('cash-closures.pending')
            ->with('success', 'Caja cerrada correctamente.');
    }

    private function resolvePeriodStart(): Carbon
    {
        $lastClosure = CashClosure::query()->latest('period_end')->first();

        if ($lastClosure) {
            return $lastClosure->period_end;
        }

        $firstPaymentDate = Payment::query()->min('paid_at');

        return $firstPaymentDate ? Carbon::parse($firstPaymentDate) : now();
    }
}
