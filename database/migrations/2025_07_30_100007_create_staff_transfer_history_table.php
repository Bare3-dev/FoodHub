<?php

declare(strict_types=1);

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
        Schema::create('staff_transfer_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_restaurant_id')->nullable()->constrained('restaurants')->onDelete('set null');
            $table->foreignId('to_restaurant_id')->nullable()->constrained('restaurants')->onDelete('set null');
            $table->foreignId('from_branch_id')->nullable()->constrained('restaurant_branches')->onDelete('set null');
            $table->foreignId('to_branch_id')->nullable()->constrained('restaurant_branches')->onDelete('set null');
            $table->enum('transfer_type', [
                'restaurant_to_restaurant',  // Between different restaurants
                'branch_to_branch',         // Between branches of same restaurant
                'restaurant_to_branch',     // From restaurant level to specific branch
                'branch_to_restaurant',     // From branch to restaurant level
                'temporary_assignment',     // Temporary assignment
                'permanent_transfer'        // Permanent transfer
            ]);
            $table->enum('status', [
                'pending',      // Transfer request pending approval
                'approved',     // Transfer approved
                'rejected',     // Transfer rejected
                'completed',    // Transfer completed
                'cancelled'     // Transfer cancelled
            ])->default('pending');
            $table->text('transfer_reason');
            $table->text('additional_notes')->nullable();
            $table->date('effective_date'); // When transfer should take effect
            $table->date('actual_transfer_date')->nullable(); // When transfer actually happened
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->jsonb('transfer_details')->default('{}'); // Additional transfer information
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['transfer_type', 'status']);
            $table->index(['from_restaurant_id', 'to_restaurant_id']);
            $table->index(['from_branch_id', 'to_branch_id']);
            $table->index(['effective_date', 'status']);
            $table->index(['requested_by', 'created_at']);
            $table->index(['approved_by', 'approved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_transfer_history');
    }
}; 