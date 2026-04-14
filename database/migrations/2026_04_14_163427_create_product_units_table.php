<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('unit_name');            // e.g., Bottle, Box, Sachet
            $table->integer('quantity_in_base');    // how many base units this represents
            $table->decimal('price', 12, 2);        // selling price for this unit
            $table->boolean('is_bulk')->default(false);
            $table->decimal('bulk_discount_pct', 5, 2)->default(0); // e.g., 10.00 = 10%
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
