<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductImageService;
use App\Services\ProductMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private ProductImageService $imageService) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category:id,name')
            ->withCount('purchases')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            })
            ->orderBy('name')
            ->paginate(15)
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
                'product_category_id'      => $product->product_category_id,
                'category_name'            => $product->category?->name,
                'image_url'                => $product->image_path ? asset('storage/' . $product->image_path) : null,
            ]);

        return Inertia::render('Products/Index', [
            'products'   => $products,
            'filters'    => $request->only('search'),
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->imageService->store($request->file('image'));
        }

        Product::create($data);

        return back()->with('success', 'Producto creado correctamente.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->imageService->replace($product->image_path, $request->file('image'));
        }

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

    // Quita la imagen de un producto sin tener que editarlo completo
    public function removeImage(Product $product): RedirectResponse
    {
        $this->imageService->delete($product->image_path);
        $product->update(['image_path' => null]);

        return back()->with('success', 'Imagen eliminada.');
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

    // Menú agrupado por categoría — lo consume la pantalla de toma de pedido (Tables/Show.vue).
    // Solo trae productos activos; el stock se valida en el momento de agregar al pedido,
    // no aquí, para que el menú siga mostrando el producto aunque esté temporalmente agotado.
    public function menu(ProductMenuService $menuService)
    {
        return response()->json(['categories' => $menuService->grouped()]);
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
            'product_category_id'      => ['nullable', 'exists:product_categories,id'],
            'image'                    => ['nullable', 'image', 'max:4096'], // 4MB máx. de subida, antes de optimizar
        ], [
            'sale_price.gte'               => 'El precio de venta no puede ser menor al costo del producto.',
            'units_per_purchase_unit.min'  => 'Si el producto se compra empaquetado, el paquete debe traer al menos 2 unidades.',
            'image.image'                  => 'El archivo debe ser una imagen (jpg, png, webp).',
            'image.max'                    => 'La imagen no puede pesar más de 4MB.',
        ]);

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
