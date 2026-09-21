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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            // open: en curso (sin pagar) | closed: pagado | cancelled: anulado (con o sin pago previo)
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
            // Totales denormalizados: se recalculan en cada cambio de order_items, evitan sumar en cada consulta
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0); // suma de costos, para la ganancia neta
            $table->text('cancel_reason')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'table_id']); // consulta frecuente: pedido abierto de una mesa
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
