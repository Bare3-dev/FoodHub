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
        Schema::create('shift_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('staff_shifts')->onDelete('cascade');
            $table->enum('conflict_type', [
                'overlap',           // Shift overlaps with another shift
                'unavailable',       // Staff is not available at this time
                'max_hours',         // Exceeds maximum weekly hours
                'min_rest',          // Insufficient rest between shifts
                'branch_mismatch',   // Staff assigned to wrong branch
                'role_mismatch'      // Staff role doesn't match shift requirements
            ]);
            $table->jsonb('conflict_details')->default('{}'); // Detailed conflict information
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['shift_id', 'is_resolved']);
            $table->index(['conflict_type', 'severity']);
            $table->index(['is_resolved', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_conflicts');
    }
}; 