<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Worker')->after('email'); // Admin | Worker
            $table->string('status')->default('active')->after('role'); // active | inactive
            $table->string('contact')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('contact');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'contact', 'last_login_at']);
        });
    }
};
