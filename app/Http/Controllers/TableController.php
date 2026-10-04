<?php

namespace App\Http\Controllers;

use App\Services\ProductMenuService;
use App\Models\Table;
use Inertia\Inertia;

class TableController extends Controller
{
    // Panel principal: tarjetas de mesas con estado y total acumulado si está ocupada
    public function index()
    {
        $tables = Table::query()
            ->with(['activeOrder:id,table_id,customer_name,total'])
            ->orderBy('name')
            ->get()
            ->map(fn(Table $table) => [
                'id'       => $table->id,
                'name'     => $table->name,
                'capacity' => $table->capacity,
                'status'   => $table->status,
                'total'    => $table->activeOrder?->total ?? 0,
                'customer_name' => $table->activeOrder?->customer_name,
            ]);

        return Inertia::render('Tables/Index', ['tables' => $tables]);
    }

    // Detalle de mesa: pedido activo con sus items + catálogo de productos para agregar
    public function show(Table $table, ProductMenuService $menuService)
    {
        $table->load(['activeOrder.items.product:id,name']);

        return Inertia::render('Tables/Show', [
            'table'           => $table,
            'otherFreeTables' => Table::query()
                ->where('status', 'free')
                ->where('id', '!=', $table->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'menu' => $menuService->grouped(),
        ]);
    }
}
