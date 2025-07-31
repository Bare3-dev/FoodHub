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
        Schema::create('branch_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 8, 2);
            $table->boolean('is_available')->default(true);
            $table->integer('preparation_time')->nullable(); // in minutes
            $table->text('special_instructions')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['restaurant_branch_id', 'is_available']);
            $table->index(['menu_item_id']);
            $table->unique(['restaurant_branch_id', 'menu_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_menu_items');
    }
};
