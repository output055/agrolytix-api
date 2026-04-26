<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesale_sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('wholesale_product_id')->constrained();
            $table->string('product_name');
            $table->string('unit_name');
            $table->integer('quantity');
            $table->integer('quantity_base');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_sale_items');
    }
};
