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
        Schema::create('webhook_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // mada, stc_pay, square, toast, etc.
            $table->string('event_type'); // payment_success, order_update, etc.
            $table->string('webhook_url');
            $table->string('webhook_id')->nullable(); // External service webhook ID
            $table->text('signature_key')->nullable(); // For signature verification
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable(); // Additional service-specific config
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['service', 'event_type']);
            $table->index('is_active');
            $table->unique(['service', 'event_type', 'webhook_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_registrations');
    }
}; 