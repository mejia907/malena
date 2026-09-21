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
        Schema::create('cash_closures', function (Blueprint $table) {
            $table->id();
            $table->timestamp('period_start'); // fin del cierre anterior (o inicio de operaciones)
            $table->timestamp('period_end');   // momento del clic en "Cerrar caja"
            $table->decimal('total_sales', 10, 2);
            $table->decimal('total_cash', 10, 2);
            $table->decimal('total_transfer', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->decimal('total_profit', 10, 2);
            $table->unsignedInteger('orders_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_closures');
    }
};
