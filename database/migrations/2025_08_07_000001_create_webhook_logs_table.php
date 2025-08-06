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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // mada, stc_pay, apple_pay, google_pay, etc.
            $table->string('event_type'); // payment_update, pos_update, etc.
            $table->json('payload'); // Sanitized webhook payload
            $table->boolean('success');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->integer('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Indexes for performance and querying
            $table->index(['service', 'event_type']);
            $table->index(['success', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
}; 