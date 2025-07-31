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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('target_type')->nullable(); // Add target_type column
            $table->unsignedBigInteger('target_id')->nullable(); // Add target_id column
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['target_type', 'target_id']); // Add index for target columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
