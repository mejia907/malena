<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Si el producto se compra empaquetado (ej: "paquete", "caja", "cartón")
            // pero se vende individual. Null = se compra y se vende igual, sin conversión.
            $table->string('purchase_unit_label')->nullable()->after('name');
            $table->unsignedInteger('units_per_purchase_unit')->nullable()->after('purchase_unit_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit_label', 'units_per_purchase_unit']);
        });
    }
};
