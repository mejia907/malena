<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $i) {
            Table::create(['name' => "Mesa {$i}", 'capacity' => 4]);
        }
    }
}