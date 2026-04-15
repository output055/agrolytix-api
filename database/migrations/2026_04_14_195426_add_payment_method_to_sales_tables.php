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
            $table->string('payment_method')->default('Cash')->after('profit');
        });

        Schema::table('wholesale_sales', function (Blueprint $table) {
            $table->string('payment_method')->default('Cash')->after('profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retail_sales', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('wholesale_sales', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
