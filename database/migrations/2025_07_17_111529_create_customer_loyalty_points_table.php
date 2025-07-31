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
        Schema::create('customer_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->foreignId('loyalty_tier_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('current_points', 10, 2)->default(0.00);
            $table->decimal('total_points_earned', 10, 2)->default(0.00);
            $table->decimal('total_points_redeemed', 10, 2)->default(0.00);
            $table->decimal('total_points_expired', 10, 2)->default(0.00);
            $table->date('last_points_earned_date')->nullable();
            $table->date('last_points_redeemed_date')->nullable();
            $table->date('points_expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('bonus_multipliers_used')->nullable();
            $table->json('redemption_history')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'loyalty_program_id']);
            $table->index(['loyalty_tier_id']);
            $table->index(['points_expiry_date']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_points');
    }
};
