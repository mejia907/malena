<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::insert([
            ['name' => 'Café', 'cost_price' => 1500, 'sale_price' => 4000, 'stock' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Empanada', 'cost_price' => 2200, 'sale_price' => 6000, 'stock' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pastel de pollo', 'cost_price' => 1800, 'sale_price' => 5000, 'stock' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}