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
        Schema::create('api_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 10)->unique(); // e.g., 'v1', 'v2'
            $table->enum('status', ['active', 'deprecated', 'sunset', 'beta'])->default('active');
            $table->timestamp('release_date')->nullable();
            $table->timestamp('sunset_date')->nullable();
            $table->string('migration_guide_url')->nullable();
            $table->json('breaking_changes')->nullable(); // Array of breaking changes
            $table->boolean('is_default')->default(false);
            $table->string('min_client_version')->nullable(); // Minimum client version required
            $table->string('max_client_version')->nullable(); // Maximum client version supported
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'sunset_date']);
            $table->index(['is_default']);
            $table->index(['release_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_versions');
    }
};
