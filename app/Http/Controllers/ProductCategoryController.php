<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
  public function index(): Response
  {
    $categories = ProductCategory::query()
      ->withCount('products')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get();

    return Inertia::render('ProductCategories/Index', ['categories' => $categories]);
  }

  public function store(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:100', 'unique:product_categories,name'],
    ]);

    // Se asigna automáticamente al final de la lista — el usuario nunca digita un número
    $nextOrder = (int) ProductCategory::query()->max('sort_order') + 1;

    ProductCategory::create([
      'name'       => $data['name'],
      'sort_order' => $nextOrder,
    ]);

    return back()->with('success', 'Categoría creada correctamente.');
  }

  public function update(Request $request, ProductCategory $productCategory): RedirectResponse
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:100', 'unique:product_categories,name,' . $productCategory->id],
    ]);

    $productCategory->update($data);

    return back()->with('success', 'Categoría actualizada correctamente.');
  }

  public function destroy(ProductCategory $productCategory): RedirectResponse
  {
    if ($productCategory->products()->exists()) {
      return back()->withErrors([
        'category' => 'No puedes eliminar una categoría que todavía tiene productos asignados. Muévelos primero a otra categoría.',
      ]);
    }

    $productCategory->delete();

    return back()->with('success', 'Categoría eliminada.');
  }

  // Recibe el nuevo orden completo (lista de IDs en el orden visual tras arrastrar)
  // y actualiza el sort_order de todas las categorías en una sola transacción.
  public function reorder(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'ids'   => ['required', 'array'],
      'ids.*' => ['integer', 'exists:product_categories,id'],
    ]);

    DB::transaction(function () use ($data) {
      foreach ($data['ids'] as $index => $id) {
        ProductCategory::where('id', $id)->update(['sort_order' => $index]);
      }
    });

    return back(303); // 303 evita reenviar el PATCH si el usuario recarga justo después
  }
}
