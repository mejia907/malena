<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            // Cantidad comprada en la unidad de compra (ej: 5 paquetes, o 20 unidades sueltas)
            $table->unsignedInteger('purchase_quantity');
            // Lo que realmente se pagó en total por esta compra (ej: $250.000 por 5 paquetes)
            $table->decimal('purchase_total_cost', 10, 2);
            // Unidades individuales que esta compra sumó al stock (ya convertidas)
            $table->unsignedInteger('units_added');
            // Costo por unidad individual resultante SOLO de esta compra (antes de promediar)
            $table->decimal('unit_cost', 10, 2);
            $table->text('note')->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'purchased_at']); // historial de compras por producto
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchases');
    }
};
