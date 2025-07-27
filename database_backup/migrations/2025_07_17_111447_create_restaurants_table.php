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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cuisine_type');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->jsonb('business_hours'); // PostgreSQL JSONB for structured data
            $table->jsonb('settings')->default('{}'); // Restaurant-specific settings
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('commission_rate', 5, 2)->default(0.00); // Platform commission percentage
            $table->boolean('is_featured')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['status', 'created_at']);
            $table->index('cuisine_type');
            $table->index('is_featured');
            $table->index('slug');
            
            // Removed: Full-text search index for PostgreSQL (will be in a separate migration)
            // DB::statement('CREATE INDEX restaurants_search_idx ON restaurants USING gin(to_tsvector(\'english\', name || \' \' || COALESCE(description, \'\')))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
