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
        Schema::table('branch_menu_items', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->after('is_available');
            $table->integer('min_stock_threshold')->default(5)->after('stock_quantity');
            $table->boolean('track_inventory')->default(true)->after('min_stock_threshold');
            $table->timestamp('last_stock_update')->nullable()->after('track_inventory');
            
            // Index for inventory queries
            $table->index(['stock_quantity', 'track_inventory']);
            $table->index(['restaurant_branch_id', 'stock_quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_menu_items', function (Blueprint $table) {
            $table->dropIndex(['stock_quantity', 'track_inventory']);
            $table->dropIndex(['restaurant_branch_id', 'stock_quantity']);
            $table->dropColumn(['stock_quantity', 'min_stock_threshold', 'track_inventory', 'last_stock_update']);
        });
    }
};
