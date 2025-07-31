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
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Staff member being rated
            $table->integer('rating')->comment('1-5 star rating');
            $table->enum('feedback_type', [
                'food_quality',      // Taste, presentation, temperature
                'service',           // Staff friendliness, speed, accuracy
                'delivery',          // Delivery time, packaging, driver
                'overall',           // Overall experience
                'cleanliness',       // Restaurant/branch cleanliness
                'value_for_money',   // Price vs quality
                'menu_variety',      // Menu selection and options
                'special_requests'   // Handling of special requests
            ]);
            $table->text('feedback_text')->nullable();
            $table->jsonb('feedback_details')->default('{}'); // Structured feedback data
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_verified_purchase')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('moderation_notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['order_id', 'feedback_type']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['restaurant_id', 'rating']);
            $table->index(['restaurant_branch_id', 'rating']);
            $table->index(['user_id', 'rating']);
            $table->index(['feedback_type', 'rating']);
            $table->index(['status', 'created_at']);
            $table->index('rating');
            
            // Note: Rating validation is handled at the application level
            // since Laravel Blueprint doesn't support CHECK constraints
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_feedback');
    }
}; 