<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_engagement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('challenge_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('event_type', [
                'challenge_viewed',
                'progress_checked',
                'leaderboard_viewed',
                'challenge_shared',
                'notification_clicked',
                'reward_claimed'
            ]);
            $table->string('source')->nullable(); // 'mobile_app', 'web', 'notification'
            $table->json('event_data')->nullable(); // Additional event context
            $table->timestamp('event_timestamp');
            $table->string('session_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'event_timestamp']);
            $table->index(['challenge_id', 'event_type']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_engagement_logs');
    }
};