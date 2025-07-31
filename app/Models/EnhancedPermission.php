<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

final class EnhancedPermission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'role',
        'permission',
        'scope',
        'scope_id',
        'is_active',
        'conditions',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant for this permission (when scope is restaurant).
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'scope_id');
    }

    /**
     * Get the branch for this permission (when scope is branch).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'scope_id');
    }

    /**
     * Check if this permission is global.
     */
    public function isGlobal(): bool
    {
        return $this->scope === 'global';
    }

    /**
     * Check if this permission is restaurant-scoped.
     */
    public function isRestaurantScoped(): bool
    {
        return $this->scope === 'restaurant';
    }

    /**
     * Check if this permission is branch-scoped.
     */
    public function isBranchScoped(): bool
    {
        return $this->scope === 'branch';
    }

    /**
     * Get permission description.
     */
    public function getPermissionDescription(): string
    {
        $descriptions = [
            'menu.manage' => 'Manage menu items and categories',
            'menu.view' => 'View menu items and categories',
            'orders.manage' => 'Manage all orders',
            'orders.view' => 'View orders',
            'orders.create' => 'Create new orders',
            'orders.update' => 'Update existing orders',
            'orders.cancel' => 'Cancel orders',
            'customers.manage' => 'Manage customer information',
            'customers.view' => 'View customer information',
            'staff.manage' => 'Manage staff members',
            'staff.view' => 'View staff information',
            'staff.assign' => 'Assign staff to shifts',
            'reports.view' => 'View reports and analytics',
            'reports.generate' => 'Generate reports',
            'settings.manage' => 'Manage system settings',
            'settings.view' => 'View system settings',
            'loyalty.manage' => 'Manage loyalty programs',
            'loyalty.view' => 'View loyalty programs',
            'delivery.manage' => 'Manage delivery operations',
            'delivery.view' => 'View delivery information',
            'kitchen.manage' => 'Manage kitchen operations',
            'kitchen.view' => 'View kitchen information',
            'finance.manage' => 'Manage financial operations',
            'finance.view' => 'View financial information',
            'analytics.view' => 'View analytics and metrics',
            'analytics.manage' => 'Manage analytics settings',
        ];

        return $descriptions[$this->permission] ?? 'Unknown permission';
    }

    /**
     * Get scope description.
     */
    public function getScopeDescription(): string
    {
        $descriptions = [
            'global' => 'Global (all restaurants and branches)',
            'restaurant' => 'Restaurant-specific',
            'branch' => 'Branch-specific',
        ];

        return $descriptions[$this->scope] ?? 'Unknown scope';
    }

    /**
     * Check if permission applies to a specific restaurant.
     */
    public function appliesToRestaurant(int $restaurantId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        if ($this->isRestaurantScoped()) {
            return $this->scope_id === $restaurantId;
        }

        if ($this->isBranchScoped()) {
            $branch = RestaurantBranch::find($this->scope_id);
            return $branch && $branch->restaurant_id === $restaurantId;
        }

        return false;
    }

    /**
     * Check if permission applies to a specific branch.
     */
    public function appliesToBranch(int $branchId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        if ($this->isRestaurantScoped()) {
            $branch = RestaurantBranch::find($branchId);
            return $branch && $branch->restaurant_id === $this->scope_id;
        }

        if ($this->isBranchScoped()) {
            return $this->scope_id === $branchId;
        }

        return false;
    }

    /**
     * Check if permission has specific conditions.
     */
    public function hasConditions(): bool
    {
        return !empty($this->conditions);
    }

    /**
     * Get specific condition value.
     */
    public function getCondition(string $key)
    {
        return $this->conditions[$key] ?? null;
    }

    /**
     * Scope to get active permissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get permissions for a specific role.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get permissions by scope.
     */
    public function scopeByScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope to get global permissions.
     */
    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    /**
     * Scope to get restaurant-scoped permissions.
     */
    public function scopeRestaurantScoped($query)
    {
        return $query->where('scope', 'restaurant');
    }

    /**
     * Scope to get branch-scoped permissions.
     */
    public function scopeBranchScoped($query)
    {
        return $query->where('scope', 'branch');
    }

    /**
     * Scope to get permissions for a specific permission name.
     */
    public function scopeForPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    /**
     * Scope to get permissions for a specific scope ID.
     */
    public function scopeForScopeId($query, int $scopeId)
    {
        return $query->where('scope_id', $scopeId);
    }
} 