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
        Schema::table('orders', function (Blueprint $table) {
            // Add discount percentage fields for pricing calculations
            $table->decimal('tier_discount_percentage', 5, 2)->default(0.00)->after('loyalty_points_used');
            $table->decimal('coupon_discount_percentage', 5, 2)->default(0.00)->after('tier_discount_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tier_discount_percentage', 'coupon_discount_percentage']);
        });
    }
};
