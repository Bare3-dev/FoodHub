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
        Schema::create('spin_wheels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('daily_free_spins_base')->default(1);
            $table->integer('max_daily_spins')->default(5);
            $table->decimal('spin_cost_points', 10, 2)->default(100.00);
            $table->json('tier_spin_multipliers')->nullable(); // Extra spins per tier
            $table->json('tier_probability_boost')->nullable(); // Probability boost per tier
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spin_wheels');
    }
}; 