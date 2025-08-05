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
        Schema::create('customer_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('spin_wheel_id')->constrained()->onDelete('cascade');
            $table->integer('free_spins_remaining')->default(0);
            $table->integer('paid_spins_remaining')->default(0);
            $table->integer('total_spins_used')->default(0);
            $table->integer('daily_spins_used')->default(0);
            $table->date('last_spin_date')->nullable();
            $table->timestamp('last_spin_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->unique(['customer_id', 'spin_wheel_id']);
            $table->index(['customer_id', 'is_active']);
            $table->index(['last_spin_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_spins');
    }
}; 