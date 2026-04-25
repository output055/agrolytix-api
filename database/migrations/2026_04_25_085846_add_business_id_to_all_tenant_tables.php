<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $tables = [
        'users',
        'products',
        'product_units',
        'wholesale_products',
        'wholesale_product_units',
        'retail_sales',
        'retail_sale_items',
        'clients',
        'wholesale_sales',
        'wholesale_sale_items',
        'client_sales',
        'reversals',
        'debts',
        'audit_logs',
        'expenses',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First create a default business for existing records
        $defaultBusinessId = \DB::table('businesses')->insertGetId([
            'name' => 'Default Business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($defaultBusinessId) {
                $table->foreignId('business_id')->default($defaultBusinessId)->constrained('businesses')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            });
        }
    }
};
