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
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Bronze, Silver, Gold, Platinum, Diamond
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->decimal('min_points_required', 10, 2);
            $table->decimal('max_points_capacity', 10, 2)->nullable();
            $table->decimal('points_multiplier', 5, 2)->default(1.00); // Points earning multiplier
            $table->decimal('discount_percentage', 5, 2)->default(0.00); // Discount on orders
            $table->boolean('free_delivery')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('exclusive_offers')->default(false);
            $table->boolean('birthday_reward')->default(false);
            $table->json('additional_benefits')->nullable();
            $table->string('color_code')->nullable(); // For UI display
            $table->string('icon')->nullable(); // For UI display
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['loyalty_program_id', 'min_points_required']);
            $table->index(['is_active']);
            $table->index(['sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
