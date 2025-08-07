<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('action_type', [
                'order_placed',
                'review_written',
                'friend_referred',
                'item_tried',
                'amount_spent',
                'milestone_reached',
                'manual_adjustment'
            ]);
            $table->decimal('progress_before', 10, 2);
            $table->decimal('progress_after', 10, 2);
            $table->decimal('progress_increment', 10, 2);
            $table->text('description');
            $table->json('event_data')->nullable(); // Details about the triggering event
            $table->boolean('milestone_reached')->default(false);
            $table->string('milestone_type')->nullable(); // '25%', '50%', '75%', 'completed'
            $table->timestamps();
            
            $table->index(['customer_challenge_id', 'created_at']);
            $table->index(['customer_id', 'challenge_id']);
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_progress_logs');
    }
};