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
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['points', 'stamps', 'tiers', 'challenges'])->default('points');
            $table->boolean('is_active')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->jsonb('rules')->default('{}'); // Program-specific rules and configuration
            $table->decimal('points_per_dollar', 5, 2)->default(1.00); // Points earned per dollar spent
            $table->decimal('dollar_per_point', 5, 2)->default(0.01); // Dollar value per point when redeeming
            $table->integer('minimum_spend_for_points')->default(0); // Minimum order amount to earn points
            $table->jsonb('bonus_multipliers')->default('{}'); // Special multipliers for certain conditions
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
