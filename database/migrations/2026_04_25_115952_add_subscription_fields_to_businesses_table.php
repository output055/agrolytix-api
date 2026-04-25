<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('subscription_status')->default('trialing')->after('trial_ends_at');
            $table->string('subscription_plan')->nullable()->after('subscription_status');
            $table->string('paystack_customer_code')->nullable()->after('subscription_plan');
            $table->string('paystack_subscription_code')->nullable()->after('paystack_customer_code');
            $table->string('paystack_email_token')->nullable()->after('paystack_subscription_code');
            $table->timestamp('subscription_ends_at')->nullable()->after('paystack_email_token');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status',
                'subscription_plan',
                'paystack_customer_code',
                'paystack_subscription_code',
                'paystack_email_token',
                'subscription_ends_at',
            ]);
        });
    }
};
