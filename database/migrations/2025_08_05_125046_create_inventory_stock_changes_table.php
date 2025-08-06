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
        Schema::create('inventory_stock_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_menu_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('change_type'); // 'manual', 'pos_sync', 'order_consumption', 'restock'
            $table->integer('quantity_change'); // positive for additions, negative for reductions
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('source')->nullable(); // 'pos_system', 'manual_entry', 'order_system', etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['branch_menu_item_id', 'created_at']);
            $table->index(['change_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_changes');
    }
};
