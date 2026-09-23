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
        Schema::table('cash_closures', function (Blueprint $table) {
            $table->decimal('total_waste_cost', 10, 2)->default(0)->after('total_profit');
            $table->decimal('net_profit_after_waste', 10, 2)->default(0)->after('total_waste_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closures', function (Blueprint $table) {
            $table->dropColumn(['total_waste_cost', 'net_profit_after_waste']);
        });
    }
};
