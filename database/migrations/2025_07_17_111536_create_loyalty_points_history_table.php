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
        Schema::create('loyalty_points_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_loyalty_points_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('transaction_type'); // earned, redeemed, expired, adjusted, bonus
            $table->decimal('points_amount', 10, 2);
            $table->decimal('points_balance_after', 10, 2);
            $table->string('description');
            $table->json('transaction_details')->nullable(); // Order details, bonus info, etc.
            $table->string('source'); // order, bonus, referral, birthday, happy_hour, etc.
            $table->json('bonus_multipliers_applied')->nullable();
            $table->decimal('base_amount', 10, 2)->nullable(); // Original order amount
            $table->decimal('multiplier_applied', 5, 2)->default(1.00);
            $table->string('reference_id')->nullable(); // External reference
            $table->string('reference_type')->nullable(); // order, promotion, etc.
            $table->boolean('is_reversible')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_loyalty_points_id', 'transaction_type']);
            $table->index(['order_id']);
            $table->index(['source']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_points_history');
    }
};
