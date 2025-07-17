<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added missing import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_category_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('cost_price', 8, 2)->nullable(); // For profit analysis
            $table->string('currency', 3)->default('SAR');
            $table->string('sku')->nullable(); // Stock Keeping Unit
            $table->jsonb('images')->default('[]'); // Array of image URLs
            $table->integer('preparation_time')->default(15); // minutes
            $table->integer('calories')->nullable();
            $table->jsonb('nutritional_info')->default('{}'); // Protein, carbs, fat, etc.
            $table->jsonb('allergens')->default('[]'); // List of allergens
            $table->jsonb('dietary_tags')->default('[]'); // Vegetarian, vegan, gluten-free, etc.
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->enum('spice_level', ['mild', 'medium', 'hot', 'very_hot'])->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('customization_options')->default('[]'); // Size, add-ons, etc.
            $table->jsonb('pos_data')->default('{}'); // POS system integration data
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['restaurant_id', 'is_available', 'sort_order']);
            $table->index(['menu_category_id', 'is_available', 'sort_order']);
            $table->index('is_available');
            $table->index('is_featured');
            $table->index('price');
            $table->unique(['restaurant_id', 'slug']); // Unique slug per restaurant
            $table->unique(['restaurant_id', 'sku']); // Unique SKU per restaurant
            
            // Full-text search index for menu items
            DB::statement('CREATE INDEX menu_items_search_idx ON menu_items USING gin(to_tsvector(\'english\', name || \' \' || COALESCE(description, \'\') || \' \' || COALESCE(ingredients, \'\')))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
