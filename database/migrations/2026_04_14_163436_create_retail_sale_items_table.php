<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');     // snapshot at time of sale
            $table->string('unit_name');        // e.g., Bottle, Box
            $table->integer('quantity');        // in chosen unit
            $table->integer('quantity_base');   // in base units (deducted from stock)
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_sale_items');
    }
};
