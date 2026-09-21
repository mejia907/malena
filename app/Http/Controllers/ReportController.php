<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
  public function __construct(private SalesReportService $reportService) {}

  public function index(Request $request): Response
  {
    $data = $request->validate([
      'from' => ['nullable', 'date'],
      'to'   => ['nullable', 'date', 'after_or_equal:from'],
    ]);

    $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
    $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

    return Inertia::render('Reports/Index', [
      'summary'   => $this->reportService->summarize($from, $to),
      'products'  => $this->reportService->productBreakdown($from, $to),
      'filters'   => [
        'from' => $from->toDateString(),
        'to'   => $to->toDateString(),
      ],
    ]);
  }
}
