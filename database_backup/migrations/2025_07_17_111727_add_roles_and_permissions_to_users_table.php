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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade')->after('id');
            $table->foreignId('restaurant_branch_id')->nullable()->constrained()->onDelete('set null')->after('restaurant_id');
            $table->enum('role', [
                'SUPER_ADMIN',
                'RESTAURANT_OWNER', 
                'BRANCH_MANAGER',
                'CASHIER',
                'KITCHEN_STAFF',
                'DELIVERY_MANAGER',
                'CUSTOMER_SERVICE',
                'DRIVER'    // Staff roles only - customers use separate Customer model
            ])->after('password');
            $table->jsonb('permissions')->default('[]')->after('role'); // Specific permissions override
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('permissions');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->boolean('is_email_verified')->default(false)->after('email_verified_at');
            $table->string('profile_image_url')->nullable()->after('phone');
            
            // Indexes for frequently queried columns
            $table->index(['restaurant_id', 'role', 'status']);
            $table->index(['restaurant_branch_id', 'status']);
            $table->index('role');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'role', 'status']);
            $table->dropIndex(['restaurant_branch_id', 'status']);
            $table->dropIndex('role');
            $table->dropIndex('status');
            
            $table->dropForeign(['restaurant_id']);
            $table->dropForeign(['restaurant_branch_id']);
            
            $table->dropColumn([
                'restaurant_id',
                'restaurant_branch_id',
                'role',
                'permissions',
                'status',
                'phone',
                'last_login_at',
                'is_email_verified',
                'profile_image_url'
            ]);
        });
    }
};
