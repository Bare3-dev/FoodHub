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
        Schema::create('stamp_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->integer('stamps_earned')->default(0);
            $table->integer('stamps_required')->default(10);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
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
        Schema::dropIfExists('stamp_cards');
    }
};
