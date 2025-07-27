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
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_category_id')->nullable()->constrained('menu_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}'); // Category-specific settings
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['restaurant_id', 'is_active', 'sort_order']);
            $table->index(['parent_category_id', 'sort_order']);
            $table->index('is_active');
            $table->unique(['restaurant_id', 'slug']); // Unique slug per restaurant
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
