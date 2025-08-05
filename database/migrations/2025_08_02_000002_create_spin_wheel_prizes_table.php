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
        Schema::create('spin_wheel_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spin_wheel_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['discount', 'free_item', 'bonus_points', 'free_delivery', 'cashback']);
            $table->decimal('value', 10, 2); // Discount percentage, points amount, etc.
            $table->string('value_type'); // percentage, fixed_amount, points, etc.
            $table->decimal('probability', 5, 4)->default(0.1000); // 0.0000 to 1.0000
            $table->integer('max_redemptions')->nullable(); // Unlimited if null
            $table->integer('current_redemptions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('tier_restrictions')->nullable(); // Which tiers can win this prize
            $table->json('conditions')->nullable(); // Additional conditions
            $table->timestamps();
            
            // Indexes
            $table->index(['spin_wheel_id', 'is_active']);
            $table->index(['type']);
            $table->index(['probability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_prizes');
    }
}; 