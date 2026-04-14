<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesale_product_id')->constrained()->onDelete('cascade');
            $table->string('unit_name');
            $table->integer('quantity_in_base');
            $table->decimal('price', 12, 2);
            $table->boolean('is_bulk')->default(false);
            $table->decimal('bulk_discount_pct', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_product_units');
    }
};
