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
        Schema::create('analytics_dashboard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('metric_name'); // e.g., 'daily_revenue', 'customer_satisfaction', 'staff_performance'
            $table->jsonb('metric_value')->default('{}'); // Aggregated metric data
            $table->string('date_range'); // e.g., 'daily', 'weekly', 'monthly', 'yearly'
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('data_type', ['revenue', 'orders', 'customers', 'staff', 'delivery', 'kitchen'])->default('orders');
            $table->boolean('is_automated')->default(true);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['restaurant_id', 'metric_name', 'date_range']);
            $table->index(['restaurant_branch_id', 'metric_name', 'date_range']);
            $table->index(['data_type', 'date_range']);
            $table->index(['start_date', 'end_date']);
            $table->index('last_calculated_at');
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['restaurant_id', 'restaurant_branch_id', 'metric_name', 'date_range', 'start_date'], 'unique_analytics_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_dashboard');
    }
}; 