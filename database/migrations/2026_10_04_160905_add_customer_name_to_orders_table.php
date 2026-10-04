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
        Schema::table('orders', function (Blueprint $table) {
            // Vive en el pedido, no en la mesa: así desaparece solo al cerrar/cancelar
            // (el pedido deja de ser el "activeOrder" de la mesa) sin necesitar limpieza manual
            $table->string('customer_name')->nullable()->after('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_name');
        });
    }
};
