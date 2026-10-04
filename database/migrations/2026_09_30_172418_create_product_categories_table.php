<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // ej: "Cervezas", "Gaseosas"
            $table->unsignedInteger('sort_order')->default(0); // orden de aparición en las cards
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
