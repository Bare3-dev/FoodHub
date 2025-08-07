<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->datetime('assigned_at');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->enum('status', [
                'assigned',     // Challenge assigned but not started
                'active',       // Customer is working on it
                'completed',    // Challenge completed
                'rewarded',     // Reward has been given
                'expired',      // Challenge expired
                'cancelled'     // Challenge cancelled
            ])->default('assigned');
            $table->decimal('progress_current', 10, 2)->default(0); // Current progress value
            $table->decimal('progress_target', 10, 2); // Target value to complete
            $table->decimal('progress_percentage', 5, 2)->default(0); // Calculated percentage
            $table->json('progress_details')->nullable(); // Detailed progress tracking
            $table->boolean('reward_claimed')->default(false);
            $table->datetime('reward_claimed_at')->nullable();
            $table->json('reward_details')->nullable(); // Details of reward given
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();
            
            $table->unique(['customer_id', 'challenge_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['challenge_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_challenges');
    }
};