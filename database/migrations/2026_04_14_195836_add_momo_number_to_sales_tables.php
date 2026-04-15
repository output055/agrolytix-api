<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('retail_sales', function (Blueprint $table) {
            $table->string('momo_number')->nullable()->after('payment_method');
        });

        Schema::table('wholesale_sales', function (Blueprint $table) {
            $table->string('momo_number')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retail_sales', function (Blueprint $table) {
            $table->dropColumn('momo_number');
        });

        Schema::table('wholesale_sales', function (Blueprint $table) {
            $table->dropColumn('momo_number');
        });
    }
};
