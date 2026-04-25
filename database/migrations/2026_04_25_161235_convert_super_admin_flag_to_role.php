<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Migrate any existing super admin flag to the role column
            DB::table('users')->where('is_super_admin', true)->update(['role' => 'SuperAdmin']);

            // Drop the redundant column
            $table->dropColumn('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('business_id');
            DB::table('users')->where('role', 'SuperAdmin')->update(['is_super_admin' => true]);
        });
    }
};
