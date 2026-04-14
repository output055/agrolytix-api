<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('wholesale_sale_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('debt', 12, 2)->default(0);
            $table->date('sale_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sales');
    }
};
