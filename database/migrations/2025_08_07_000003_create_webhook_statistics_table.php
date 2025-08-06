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
        Schema::create('webhook_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // mada, stc_pay, etc.
            $table->string('event_type'); // payment_update, etc.
            $table->integer('total_received')->default(0);
            $table->integer('successful_processed')->default(0);
            $table->integer('failed_processed')->default(0);
            $table->integer('average_response_time_ms')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['service', 'event_type']);
            $table->unique(['service', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_statistics');
    }
}; 