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
        Schema::create('enhanced_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // Role name (e.g., 'SUPER_ADMIN', 'RESTAURANT_OWNER')
            $table->string('permission'); // Permission name (e.g., 'menu.manage', 'orders.view')
            $table->enum('scope', ['global', 'restaurant', 'branch'])->default('global');
            $table->foreignId('scope_id')->nullable()->constrained('restaurants')->onDelete('cascade'); // restaurant_id or branch_id
            $table->boolean('is_active')->default(true);
            $table->jsonb('conditions')->default('{}'); // Additional conditions for permission
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['role', 'permission']);
            $table->index(['scope', 'scope_id']);
            $table->index(['is_active', 'role']);
            $table->index('permission');
            
            // Unique constraint to prevent duplicate permissions
            $table->unique(['role', 'permission', 'scope', 'scope_id'], 'unique_permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_permissions');
    }
}; 