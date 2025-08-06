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
        Schema::create('restaurant_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('config_key');
            $table->text('config_value');
            $table->boolean('is_encrypted')->default(false);
            $table->string('data_type')->default('string'); // string, integer, float, boolean, array, json
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->unique(['restaurant_id', 'config_key']);
            $table->index(['restaurant_id', 'is_sensitive']);
            $table->index('config_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_configs');
    }
}; 