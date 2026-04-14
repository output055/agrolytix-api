<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesale_sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained();
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('old_debt', 12, 2);
            $table->decimal('new_debt', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
