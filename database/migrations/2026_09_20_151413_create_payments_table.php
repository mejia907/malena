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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->enum('method', ['cash', 'transfer']);
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at')->useCurrent();
            // Si el pedido se anula después de pagado, se marca aquí en vez de borrar el registro
            // (conserva el historial contable) y esto excluye el pago de los reportes/cierres
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->timestamps();

            $table->index('paid_at');     // reportes por rango de fechas
            $table->index('reversed_at'); // filtrar pagos válidos vs anulados
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
