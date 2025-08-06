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
        Schema::create('inventory_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('report_type'); // 'daily', 'weekly', 'monthly', 'low_stock', 'turnover'
            $table->date('report_date');
            $table->json('report_data'); // Store the actual report data
            $table->text('summary')->nullable();
            $table->string('status')->default('generated'); // 'generated', 'sent', 'archived'
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['restaurant_id', 'report_date']);
            $table->index(['report_type', 'report_date']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reports');
    }
};
