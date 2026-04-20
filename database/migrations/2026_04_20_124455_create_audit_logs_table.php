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
        Schema::create('audit_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->string('action_type')->index(); // e.g., 'CREATE_USER', 'LOGIN', 'RESTOCK'
            $blueprint->string('entity_type')->nullable()->index(); // e.g., 'product', 'user'
            $blueprint->unsignedBigInteger('entity_id')->nullable()->index();
            $blueprint->string('status')->default('success'); // 'success', 'failure'
            $blueprint->string('severity')->default('INFO'); // 'INFO', 'WARNING', 'CRITICAL'
            $blueprint->string('ip_address', 45)->nullable();
            $blueprint->text('user_agent')->nullable();
            $blueprint->json('metadata')->nullable(); // Stores { old: [], new: [], context: "" }
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
