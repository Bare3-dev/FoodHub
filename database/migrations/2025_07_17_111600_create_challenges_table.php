<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->enum('challenge_type', [
                'frequency',    // Order X times
                'variety',      // Try X different items
                'value',        // Spend X amount
                'social',       // Share/Review/Refer
                'seasonal',     // Holiday/Event specific
                'referral'      // Refer friends
            ]);
            $table->json('requirements'); // Challenge-specific requirements
            $table->enum('reward_type', ['points', 'discount', 'free_item', 'coupon']);
            $table->decimal('reward_value', 10, 2); // Points amount, discount %, item value
            $table->json('reward_metadata')->nullable(); // Additional reward details
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->integer('duration_days')->nullable(); // For individual assignment duration
            $table->json('target_segments')->nullable(); // Customer segments or criteria
            $table->boolean('is_active')->default(true);
            $table->boolean('is_repeatable')->default(false);
            $table->integer('max_participants')->nullable();
            $table->integer('priority')->default(1); // Display priority
            $table->json('metadata')->nullable(); // Additional configuration
            $table->timestamps();
            
            $table->index(['challenge_type', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};