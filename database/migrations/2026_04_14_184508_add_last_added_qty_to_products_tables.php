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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('last_added_qty')->default(0)->after('quantity');
        });
        
        Schema::table('wholesale_products', function (Blueprint $table) {
            $table->integer('last_added_qty')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('last_added_qty');
        });
        
        Schema::table('wholesale_products', function (Blueprint $table) {
            $table->dropColumn('last_added_qty');
        });
    }
};
