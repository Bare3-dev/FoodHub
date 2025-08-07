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
        Schema::create('pos_order_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('foodhub_order_id');
            $table->string('pos_order_id', 255);
            $table->string('pos_type', 50);
            $table->enum('sync_status', ['synced', 'failed', 'pending'])->default('pending');
            $table->timestamps();

            $table->primary(['foodhub_order_id', 'pos_order_id']);
            $table->foreign('foodhub_order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['pos_type', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_order_mappings');
    }
}; 