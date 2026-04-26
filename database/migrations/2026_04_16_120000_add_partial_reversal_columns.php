<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reversals', function (Blueprint $table) {
            $table->json('reversed_items')->nullable()->after('reason')
                  ->comment('JSON array of {item_id, quantity_base, subtotal, cost_price}');
            $table->decimal('cost_reversed', 12, 2)->default(0)->after('amount_reversed');
            $table->boolean('is_partial')->default(false)->after('cost_reversed');
        });
    }

    public function down(): void
    {
        Schema::table('reversals', function (Blueprint $table) {
            $table->dropColumn(['reversed_items', 'cost_reversed', 'is_partial']);
        });
    }
};
