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
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
            $table->foreign('parent_id')->references('id')->on('businesses')->onDelete('restrict');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('to_business_id')->nullable()->after('to_product_name')->index();
            $table->foreign('to_business_id')->references('id')->on('businesses')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['to_business_id']);
            $table->dropColumn('to_business_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
