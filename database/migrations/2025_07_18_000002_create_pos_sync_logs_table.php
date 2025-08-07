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
        Schema::create('pos_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_integration_id');
            $table->enum('sync_type', ['order', 'menu', 'inventory']);
            $table->enum('status', ['success', 'failed', 'pending']);
            $table->json('details')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->foreign('pos_integration_id')->references('id')->on('pos_integrations')->onDelete('cascade');
            $table->index(['pos_integration_id', 'sync_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sync_logs');
    }
}; 