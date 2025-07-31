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
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_branch_id')->constrained()->onDelete('cascade');
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', [
                'scheduled',    // Shift is planned but not started
                'active',       // Staff is currently working
                'completed',    // Shift finished successfully
                'cancelled',    // Shift was cancelled
                'no_show'       // Staff didn't show up
            ])->default('scheduled');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('break_times')->default('[]'); // Array of break start/end times
            $table->decimal('total_hours', 5, 2)->nullable(); // Calculated hours worked
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'shift_date']);
            $table->index(['restaurant_branch_id', 'shift_date']);
            $table->index(['status', 'shift_date']);
            $table->index(['user_id', 'status']);
            
            // Unique constraint to prevent duplicate shifts
            $table->unique(['user_id', 'shift_date', 'start_time'], 'unique_user_shift');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
}; 