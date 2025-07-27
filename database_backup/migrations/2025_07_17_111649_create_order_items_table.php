<?php

declare(strict_types=1);

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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->string('item_name'); // Snapshot of item name at time of order
            $table->text('item_description')->nullable(); // Snapshot of description
            $table->decimal('unit_price', 8, 2); // Price per item at time of order
            $table->integer('quantity');
            $table->decimal('total_price', 8, 2); // unit_price * quantity + customizations
            $table->jsonb('customizations')->default('[]'); // Selected customizations with prices
            $table->text('special_instructions')->nullable(); // Item-specific instructions
            $table->jsonb('nutritional_snapshot')->default('{}'); // Nutritional info at time of order
            $table->jsonb('allergens_snapshot')->default('[]'); // Allergens info at time of order
            $table->string('sku')->nullable(); // SKU snapshot for inventory tracking
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['order_id', 'created_at']);
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
