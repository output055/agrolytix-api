<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->enum('from_type', ['retail', 'wholesale']);
            $table->unsignedBigInteger('from_product_id');
            $table->string('from_product_name'); // snapshot in case product deleted
            $table->enum('to_type', ['retail', 'wholesale']);
            $table->unsignedBigInteger('to_product_id');
            $table->string('to_product_name');   // snapshot
            $table->boolean('auto_created')->default(false); // was destination auto-created?
            $table->unsignedInteger('quantity');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('transferred_by');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('transferred_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
