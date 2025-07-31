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
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->nullable();
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'cancelled'])->default('assigned');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['driver_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_assignments');
    }
};
