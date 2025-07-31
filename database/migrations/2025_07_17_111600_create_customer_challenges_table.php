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
        Schema::create('customer_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('challenge_name');
            $table->text('description')->nullable();
            $table->integer('target_value');
            $table->integer('current_value')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('reward_points')->default(0);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['loyalty_program_id', 'customer_id']);
            $table->index(['is_completed']);
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_challenges');
    }
};
