<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('Worker who processed the sale');
            $table->foreignId('client_id')->constrained();
            $table->string('receipt_number')->unique();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('debt', 12, 2)->default(0);
            $table->string('status')->default('completed'); // completed | partial | reversed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_sales');
    }
};
