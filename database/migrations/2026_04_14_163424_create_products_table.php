<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sell_price', 12, 2)->default(0); // default base unit price
            $table->integer('quantity')->default(0);          // base unit stock
            $table->string('base_unit')->default('piece');    // e.g., piece, bottle, litre
            $table->integer('low_stock_alert')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
