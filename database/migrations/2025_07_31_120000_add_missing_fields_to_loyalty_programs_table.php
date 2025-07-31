<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            // Add fields that tests expect
            $table->string('currency_name')->default('points');
            $table->decimal('points_per_currency', 8, 2)->default(1.00);
            $table->integer('minimum_points_redemption')->default(100);
            $table->decimal('redemption_rate', 8, 4)->default(0.01);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn([
                'currency_name',
                'points_per_currency', 
                'minimum_points_redemption',
                'redemption_rate'
            ]);
        });
    }
}; 