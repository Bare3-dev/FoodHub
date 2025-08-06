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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id')->unique(); // External payment gateway transaction ID
            $table->string('gateway'); // mada, stc_pay, apple_pay, google_pay
            $table->string('status'); // pending, completed, failed, refunded
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_response')->nullable(); // Raw response from payment gateway
            $table->text('error_message')->nullable();
            $table->string('payment_method')->nullable(); // card, wallet, etc.
            $table->json('metadata')->nullable(); // Additional payment data
            $table->timestamps();
            
            // Indexes
            $table->index(['gateway', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
}; 