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
        Schema::create('product_wastes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            // Snapshot del costo al momento de registrar la merma (igual criterio que order_items)
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2); // quantity * unit_cost — la pérdida real en pesos
            $table->string('reason'); // motivo categorizado
            $table->text('note')->nullable();
            $table->timestamp('wasted_at')->useCurrent();
            $table->timestamps();

            $table->index('wasted_at'); // para reportes por rango de fechas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_wastes');
    }
};
