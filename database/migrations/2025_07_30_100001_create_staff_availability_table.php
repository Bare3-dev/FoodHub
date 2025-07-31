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
        Schema::create('staff_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('day_of_week'); // 1=Monday, 2=Tuesday, ..., 7=Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable(); // Reason for unavailability
            $table->date('effective_from')->nullable(); // When this availability starts
            $table->date('effective_until')->nullable(); // When this availability ends
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'day_of_week']);
            $table->index(['user_id', 'is_available']);
            $table->index(['day_of_week', 'is_available']);
            
            // Unique constraint to prevent duplicate availability entries
            $table->unique(['user_id', 'day_of_week', 'start_time'], 'unique_user_availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_availability');
    }
}; 