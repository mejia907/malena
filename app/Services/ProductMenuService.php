<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

class ProductMenuService
{
  // Productos activos agrupados por categoría (más los sin categoría bajo "Otros").
  // La usan tanto el endpoint JSON del menú como la pantalla de toma de pedido.
  public function grouped(): Collection
  {
    $categories = ProductCategory::query()
      ->with(['products' => function ($query) {
        $query->where('is_active', true)
          ->orderBy('name')
          ->select('id', 'product_category_id', 'name', 'sale_price', 'stock', 'image_path');
      }])
      ->whereHas('products', fn($query) => $query->where('is_active', true))
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn(ProductCategory $category) => [
        'id'       => $category->id,
        'name'     => $category->name,
        'products' => $this->mapProducts($category->products),
      ]);

    $uncategorized = Product::query()
      ->whereNull('product_category_id')
      ->where('is_active', true)
      ->orderBy('name')
      ->get(['id', 'name', 'sale_price', 'stock', 'image_path']);

    if ($uncategorized->isNotEmpty()) {
      $categories->push([
        'id'       => null,
        'name'     => 'Otros',
        'products' => $this->mapProducts($uncategorized),
      ]);
    }

    return $categories;
  }

  private function mapProducts(Collection $products): Collection
  {
    return $products->map(fn(Product $product) => [
      'id'         => $product->id,
      'name'       => $product->name,
      'sale_price' => $product->sale_price,
      'stock'      => $product->stock,
      'image_url'  => $product->image_path ? asset('storage/' . $product->image_path) : null,
    ]);
  }
}
