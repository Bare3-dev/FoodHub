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
        Schema::create('spin_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('spin_wheel_id')->constrained()->onDelete('cascade');
            $table->foreignId('spin_wheel_prize_id')->constrained()->onDelete('cascade');
            $table->enum('spin_type', ['free', 'paid']);
            $table->decimal('prize_value', 10, 2);
            $table->string('prize_type');
            $table->string('prize_name');
            $table->text('prize_description')->nullable();
            $table->json('prize_details')->nullable(); // Additional prize information
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by_order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['customer_id', 'spin_type']);
            $table->index(['is_redeemed']);
            $table->index(['expires_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spin_results');
    }
}; 