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
        Schema::create('stamp_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_card_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->integer('stamps_added')->default(0);
            $table->integer('stamps_before')->default(0);
            $table->integer('stamps_after')->default(0);
            $table->enum('action_type', ['stamp_earned', 'card_completed', 'reward_claimed'])->default('stamp_earned');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['stamp_card_id']);
            $table->index(['order_id']);
            $table->index(['customer_id']);
            $table->index(['action_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_history');
    }
};
