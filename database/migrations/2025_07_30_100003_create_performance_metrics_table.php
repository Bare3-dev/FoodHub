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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('metric_type', [
                'order_processing_speed',    // Average time to process orders
                'customer_satisfaction',     // Customer satisfaction scores
                'productivity',              // Orders per hour, revenue per hour
                'attendance_rate',           // Attendance percentage
                'error_rate',               // Mistakes per order
                'teamwork_score',           // Collaboration metrics
                'upsell_rate',              // Additional sales percentage
                'customer_retention',       // Repeat customer rate
                'delivery_efficiency',      // Delivery time vs estimated
                'kitchen_efficiency'        // Preparation time efficiency
            ]);
            $table->decimal('metric_value', 10, 4); // The actual metric value
            $table->string('metric_unit')->nullable(); // e.g., 'minutes', 'percentage', 'orders/hour'
            $table->date('metric_date'); // Date when metric was recorded
            $table->jsonb('metric_details')->default('{}'); // Additional context
            $table->enum('period_type', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->boolean('is_automated')->default(true); // Whether calculated automatically
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'metric_type', 'metric_date']);
            $table->index(['restaurant_branch_id', 'metric_type', 'metric_date']);
            $table->index(['restaurant_id', 'metric_type', 'metric_date']);
            $table->index(['metric_type', 'metric_date']);
            $table->index(['period_type', 'metric_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
}; 