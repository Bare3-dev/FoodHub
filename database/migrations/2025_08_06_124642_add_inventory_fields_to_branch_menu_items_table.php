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
            $table->enum('stock_status', ['available', 'limited', 'out_of_stock', 'unavailable', 'in_stock'])->default('available')->after('track_inventory');
            $table->integer('kitchen_capacity')->nullable()->after('stock_status');
            $table->integer('max_daily_orders')->nullable()->after('kitchen_capacity');
            $table->json('time_schedules')->nullable()->after('max_daily_orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_menu_items', function (Blueprint $table) {
            $table->dropColumn(['stock_status', 'kitchen_capacity', 'max_daily_orders', 'time_schedules']);
        });
    }
};
