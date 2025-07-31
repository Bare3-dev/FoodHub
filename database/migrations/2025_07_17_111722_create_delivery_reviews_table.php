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
        Schema::create('delivery_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['driver_id', 'rating']);
            $table->index(['order_id']);
            $table->index('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_reviews');
    }
};
