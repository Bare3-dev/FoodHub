<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EnhancedPermission;
use Illuminate\Database\Seeder;

final class EnhancedPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding Enhanced Permissions...');

        // Define permissions for RESTAURANT_OWNER role
        $restaurantOwnerPermissions = [
            // Menu management
            ['permission' => 'menu.manage', 'scope' => 'restaurant', 'description' => 'Manage menu items and categories'],
            ['permission' => 'menu.view', 'scope' => 'restaurant', 'description' => 'View menu items and categories'],
            
            // Order management
            ['permission' => 'orders.manage', 'scope' => 'restaurant', 'description' => 'Manage all orders'],
            ['permission' => 'orders.view', 'scope' => 'restaurant', 'description' => 'View orders'],
            ['permission' => 'orders.create', 'scope' => 'restaurant', 'description' => 'Create new orders'],
            ['permission' => 'orders.update', 'scope' => 'restaurant', 'description' => 'Update existing orders'],
            ['permission' => 'orders.cancel', 'scope' => 'restaurant', 'description' => 'Cancel orders'],
            
            // Customer management
            ['permission' => 'customers.manage', 'scope' => 'restaurant', 'description' => 'Manage customer information'],
            ['permission' => 'customers.view', 'scope' => 'restaurant', 'description' => 'View customer information'],
            
            // Staff management
            ['permission' => 'staff.manage', 'scope' => 'restaurant', 'description' => 'Manage staff members'],
            ['permission' => 'staff.view', 'scope' => 'restaurant', 'description' => 'View staff information'],
            ['permission' => 'staff.assign', 'scope' => 'restaurant', 'description' => 'Assign staff to shifts'],
            
            // Reports and analytics
            ['permission' => 'reports.view', 'scope' => 'restaurant', 'description' => 'View reports and analytics'],
            ['permission' => 'reports.generate', 'scope' => 'restaurant', 'description' => 'Generate reports'],
            
            // Settings management
            ['permission' => 'settings.manage', 'scope' => 'restaurant', 'description' => 'Manage system settings'],
            ['permission' => 'settings.view', 'scope' => 'restaurant', 'description' => 'View system settings'],
            
            // Loyalty management
            ['permission' => 'loyalty.manage', 'scope' => 'restaurant', 'description' => 'Manage loyalty programs'],
            ['permission' => 'loyalty.view', 'scope' => 'restaurant', 'description' => 'View loyalty programs'],
            
            // Delivery management
            ['permission' => 'delivery.manage', 'scope' => 'restaurant', 'description' => 'Manage delivery operations'],
            ['permission' => 'delivery.view', 'scope' => 'restaurant', 'description' => 'View delivery information'],
            
            // Kitchen management
            ['permission' => 'kitchen.manage', 'scope' => 'restaurant', 'description' => 'Manage kitchen operations'],
            ['permission' => 'kitchen.view', 'scope' => 'restaurant', 'description' => 'View kitchen information'],
            
            // Financial management
            ['permission' => 'finance.manage', 'scope' => 'restaurant', 'description' => 'Manage financial operations'],
            ['permission' => 'finance.view', 'scope' => 'restaurant', 'description' => 'View financial information'],
            
            // Analytics
            ['permission' => 'analytics.view', 'scope' => 'restaurant', 'description' => 'View analytics and metrics'],
            ['permission' => 'analytics.manage', 'scope' => 'restaurant', 'description' => 'Manage analytics settings'],
            
            // Restaurant configuration management
            ['permission' => 'view restaurant configs', 'scope' => 'restaurant', 'description' => 'View restaurant configuration settings'],
            ['permission' => 'create restaurant configs', 'scope' => 'restaurant', 'description' => 'Create restaurant configuration settings'],
            ['permission' => 'update restaurant configs', 'scope' => 'restaurant', 'description' => 'Update restaurant configuration settings'],
            ['permission' => 'delete restaurant configs', 'scope' => 'restaurant', 'description' => 'Delete restaurant configuration settings'],
            ['permission' => 'restore restaurant configs', 'scope' => 'restaurant', 'description' => 'Restore deleted restaurant configuration settings'],
            ['permission' => 'force delete restaurant configs', 'scope' => 'restaurant', 'description' => 'Permanently delete restaurant configuration settings'],
        ];

        // Create permissions for RESTAURANT_OWNER role
        foreach ($restaurantOwnerPermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'RESTAURANT_OWNER',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        // Define permissions for BRANCH_MANAGER role
        $branchManagerPermissions = [
            ['permission' => 'menu.view', 'scope' => 'branch', 'description' => 'View menu items and categories'],
            ['permission' => 'orders.view', 'scope' => 'branch', 'description' => 'View orders'],
            ['permission' => 'orders.update', 'scope' => 'branch', 'description' => 'Update existing orders'],
            ['permission' => 'customers.view', 'scope' => 'branch', 'description' => 'View customer information'],
            ['permission' => 'staff.view', 'scope' => 'branch', 'description' => 'View staff information'],
            ['permission' => 'reports.view', 'scope' => 'branch', 'description' => 'View reports and analytics'],
            ['permission' => 'delivery.view', 'scope' => 'branch', 'description' => 'View delivery information'],
            ['permission' => 'kitchen.view', 'scope' => 'branch', 'description' => 'View kitchen information'],
        ];

        // Create permissions for BRANCH_MANAGER role
        foreach ($branchManagerPermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'BRANCH_MANAGER',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        // Define permissions for CASHIER role
        $cashierPermissions = [
            ['permission' => 'menu.view', 'scope' => 'branch', 'description' => 'View menu items and categories'],
            ['permission' => 'orders.view', 'scope' => 'branch', 'description' => 'View orders'],
            ['permission' => 'orders.create', 'scope' => 'branch', 'description' => 'Create new orders'],
            ['permission' => 'orders.update', 'scope' => 'branch', 'description' => 'Update existing orders'],
            ['permission' => 'customers.view', 'scope' => 'branch', 'description' => 'View customer information'],
        ];

        // Create permissions for CASHIER role
        foreach ($cashierPermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'CASHIER',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        // Define permissions for CUSTOMER_SERVICE role
        $customerServicePermissions = [
            ['permission' => 'customers.manage', 'scope' => 'restaurant', 'description' => 'Manage customer information'],
            ['permission' => 'customers.view', 'scope' => 'restaurant', 'description' => 'View customer information'],
            ['permission' => 'orders.view', 'scope' => 'restaurant', 'description' => 'View orders'],
            ['permission' => 'reports.view', 'scope' => 'restaurant', 'description' => 'View reports and analytics'],
        ];

        // Create permissions for CUSTOMER_SERVICE role
        foreach ($customerServicePermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'CUSTOMER_SERVICE',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        // Define permissions for KITCHEN_STAFF role
        $kitchenStaffPermissions = [
            ['permission' => 'menu.view', 'scope' => 'branch', 'description' => 'View menu items and categories'],
            ['permission' => 'orders.view', 'scope' => 'branch', 'description' => 'View orders'],
            ['permission' => 'orders.update', 'scope' => 'branch', 'description' => 'Update existing orders'],
            ['permission' => 'kitchen.view', 'scope' => 'branch', 'description' => 'View kitchen information'],
        ];

        // Create permissions for KITCHEN_STAFF role
        foreach ($kitchenStaffPermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'KITCHEN_STAFF',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        // Define permissions for DRIVER role
        $driverPermissions = [
            ['permission' => 'orders.view', 'scope' => 'branch', 'description' => 'View orders'],
            ['permission' => 'orders.update', 'scope' => 'branch', 'description' => 'Update existing orders'],
            ['permission' => 'delivery.view', 'scope' => 'branch', 'description' => 'View delivery information'],
        ];

        // Create permissions for DRIVER role
        foreach ($driverPermissions as $permissionData) {
            EnhancedPermission::create([
                'role' => 'DRIVER',
                'permission' => $permissionData['permission'],
                'scope' => $permissionData['scope'],
                'is_active' => true,
                'description' => $permissionData['description'],
            ]);
        }

        $this->command->info('✅ Enhanced Permissions seeded successfully!');
        $this->command->line('📋 Created permissions for all user roles');
    }
}
