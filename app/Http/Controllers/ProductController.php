<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->withCount('purchases')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn(Product $product) => [
                'id'                       => $product->id,
                'name'                     => $product->name,
                'cost_price'               => $product->cost_price,
                'sale_price'               => $product->sale_price,
                'stock'                    => $product->stock,
                'is_active'                => $product->is_active,
                'margin'                   => $product->profit_margin,
                'purchase_unit_label'      => $product->purchase_unit_label,
                'units_per_purchase_unit'  => $product->units_per_purchase_unit,
                'purchases_count'          => $product->purchases_count,
            ]);

        return Inertia::render('Products/Index', ['products' => $products]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Product::create($data);

        return back()->with('success', 'Producto creado correctamente.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateData($request);

        $product->update($data);

        return back()->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return back()->with('success', 'Producto desactivado.');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->update(['is_active' => true]);

        return back()->with('success', 'Producto reactivado.');
    }

    // Registra una compra/reabasto de stock (paquete o unidad), sin tocar precio de venta
    public function restock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'purchase_quantity'   => ['required', 'integer', 'min:1'],
            'purchase_total_cost' => ['required', 'numeric', 'min:0.01'],
            'note'                => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($product, $data) {
            $product->restock(
                purchaseQuantity: $data['purchase_quantity'],
                purchaseTotalCost: $data['purchase_total_cost'],
                note: $data['note'] ?? null,
            );
        });

        return back()->with('success', 'Stock actualizado correctamente.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'cost_price'               => ['required', 'numeric', 'min:0'],
            'sale_price'               => ['required', 'numeric', 'min:0', 'gte:cost_price'],
            'stock'                    => ['required', 'integer', 'min:0'],
            'purchase_unit_label'      => ['nullable', 'string', 'max:50'],
            'units_per_purchase_unit'  => ['nullable', 'integer', 'min:2'],
        ], [
            'sale_price.gte'               => 'El precio de venta no puede ser menor al costo del producto.',
            'units_per_purchase_unit.min'  => 'Si el producto se compra empaquetado, el paquete debe traer al menos 2 unidades.',
        ]);

        // Si no se indicó cuántas unidades trae el paquete, tampoco tiene sentido guardar la etiqueta
        if (empty($data['units_per_purchase_unit'])) {
            $data['purchase_unit_label'] = null;
            $data['units_per_purchase_unit'] = null;
        }

        return $data;
    }

    public function registerWaste(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . $product->stock],
            'reason'   => ['required', 'in:sin_vender,dañado,consumo_interno,otro'],
            'note'     => ['nullable', 'string', 'max:255'],
        ], [
            'quantity.max' => 'No puedes registrar más merma que el stock disponible.',
        ]);

        $product->registerWaste($data['quantity'], $data['reason'], $data['note'] ?? null);

        return back()->with('success', 'Merma registrada correctamente.');
    }
}
