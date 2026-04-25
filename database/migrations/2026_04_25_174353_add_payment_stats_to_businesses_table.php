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
            $table->decimal('total_revenue', 12, 2)->default(0)->after('subscription_plan');
            $table->timestamp('last_payment_date')->nullable()->after('total_revenue');
            $table->string('last_payment_status')->nullable()->after('last_payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['total_revenue', 'last_payment_date', 'last_payment_status']);
        });
    }
};
