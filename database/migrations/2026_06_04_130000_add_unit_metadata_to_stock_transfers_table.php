<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('source_unit_id')->nullable()->after('from_product_name');
            $table->string('source_unit_name')->nullable()->after('source_unit_id');
            $table->unsignedInteger('source_unit_quantity_in_base')->default(1)->after('source_unit_name');
            $table->string('source_base_unit')->nullable()->after('source_unit_quantity_in_base');
            $table->unsignedInteger('display_quantity')->nullable()->after('auto_created');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'source_unit_id',
                'source_unit_name',
                'source_unit_quantity_in_base',
                'source_base_unit',
                'display_quantity',
            ]);
        });
    }
};
