<?php

namespace App\Http\Controllers;

use App\Models\CashClosure;
use App\Models\Payment;
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
        $summary = $this->reportService->summarize($periodStart, now());

        return Inertia::render('CashClosures/Pending', [
            'summary' => array_merge($summary, ['period_start' => $periodStart]),
            'history' => CashClosure::query()
                ->latest('period_end')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(): RedirectResponse
    {
        DB::transaction(function () {
            $periodStart = $this->resolvePeriodStart();
            $periodEnd = now();
            $summary = $this->reportService->summarize($periodStart, $periodEnd);

            CashClosure::create([
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'total_sales'    => $summary['total_sales'],
                'total_cash'     => $summary['total_cash'],
                'total_transfer' => $summary['total_transfer'],
                'total_cost'     => $summary['total_cost'],
                'total_profit'   => $summary['total_profit'],
                'orders_count'   => $summary['orders_count'],
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
