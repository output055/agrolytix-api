<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->comment('Admin who reversed the sale');
            $table->string('reason')->nullable();
            $table->decimal('amount_reversed', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversals');
    }
};
