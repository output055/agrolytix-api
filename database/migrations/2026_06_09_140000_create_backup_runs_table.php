<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('target')->default('supabase'); // e.g. supabase
            $table->string('status')->default('running');  // running | completed | failed
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->foreign('initiated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_synced')->default(0);
            $table->json('tables_synced')->nullable(); // rich per-table { table: { synced, status, error? } }
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
