<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EnhancedPermission;
use App\Models\StaffTransferHistory;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MultiRestaurantService
{
    /**
     * Check if user has permission for a specific action and scope.
     */
    public function hasPermission(User $user, string $permission, int $restaurantId = null, int $branchId = null): bool
    {
        // Super admins have all permissions
        if ($user->role === 'SUPER_ADMIN') {
            return true;
        }

        // Get all permissions for the user's role
        $permissions = EnhancedPermission::where('role', $user->role)
            ->where('is_active', true)
            ->where('permission', $permission)
            ->get();

        foreach ($permissions as $perm) {
            // Global permissions always apply
            if ($perm->isGlobal()) {
                return true;
            }

            // Restaurant-scoped permissions
            if ($perm->isRestaurantScoped() && $restaurantId) {
                if ($perm->appliesToRestaurant($restaurantId)) {
                    return true;
                }
            }

            // Branch-scoped permissions
            if ($perm->isBranchScoped() && $branchId) {
                if ($perm->appliesToBranch($branchId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get user permissions.
     */
    public function getUserPermissions(User $user): Collection
    {
        return EnhancedPermission::where('role', $user->role)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get permissions for a specific scope.
     */
    public function getPermissionsForScope(string $role, string $scope, int $scopeId = null): Collection
    {
        $query = EnhancedPermission::where('role', $role)
            ->where('is_active', true)
            ->where('scope', $scope);

        if ($scopeId) {
            $query->where('scope_id', $scopeId);
        }

        return $query->get();
    }

    /**
     * Create a new permission.
     */
    public function createPermission(array $permissionData): EnhancedPermission
    {
        return EnhancedPermission::create($permissionData);
    }

    /**
     * Update an existing permission.
     */
    public function updatePermission(EnhancedPermission $permission, array $permissionData): EnhancedPermission
    {
        $permission->update($permissionData);
        return $permission->fresh();
    }

    /**
     * Delete a permission.
     */
    public function deletePermission(EnhancedPermission $permission): bool
    {
        return $permission->delete();
    }

    /**
     * Request a staff transfer.
     */
    public function requestStaffTransfer(array $transferData): StaffTransferHistory
    {
        return DB::transaction(function () use ($transferData) {
            // Validate transfer request
            $this->validateTransferRequest($transferData);

            // Ensure status is set to pending
            $transferData['status'] = 'pending';

            // Create transfer record
            $transfer = StaffTransferHistory::create($transferData);

            // Log the transfer request
            Log::info('Staff transfer requested', [
                'user_id' => $transfer->user_id,
                'transfer_type' => $transfer->transfer_type,
                'requested_by' => $transfer->requested_by,
            ]);

            return $transfer;
        });
    }

    /**
     * Approve a staff transfer.
     */
    public function approveStaffTransfer(StaffTransferHistory $transfer, int $approvedBy, string $notes = null): StaffTransferHistory
    {
        return DB::transaction(function () use ($transfer, $approvedBy, $notes) {
            // Check if user can approve this transfer
            if (!$this->canApproveTransfer($transfer, $approvedBy)) {
                throw new \Exception('User does not have permission to approve this transfer');
            }

            // Approve the transfer
            $transfer->approve($approvedBy, $notes);

            // Log the approval
            Log::info('Staff transfer approved', [
                'transfer_id' => $transfer->id,
                'approved_by' => $approvedBy,
            ]);

            return $transfer;
        });
    }

    /**
     * Reject a staff transfer.
     */
    public function rejectStaffTransfer(StaffTransferHistory $transfer, int $rejectedBy, string $notes): StaffTransferHistory
    {
        return DB::transaction(function () use ($transfer, $rejectedBy, $notes) {
            // Check if user can reject this transfer
            if (!$this->canApproveTransfer($transfer, $rejectedBy)) {
                throw new \Exception('User does not have permission to reject this transfer');
            }

            // Reject the transfer
            $transfer->reject($rejectedBy, $notes);

            // Log the rejection
            Log::info('Staff transfer rejected', [
                'transfer_id' => $transfer->id,
                'rejected_by' => $rejectedBy,
            ]);

            return $transfer;
        });
    }

    /**
     * Complete a staff transfer.
     */
    public function completeStaffTransfer(StaffTransferHistory $transfer): StaffTransferHistory
    {
        return DB::transaction(function () use ($transfer) {
            if (!$transfer->isApproved()) {
                throw new \Exception('Transfer must be approved before completion');
            }

            // Update user's restaurant/branch assignment
            $user = $transfer->user;
            
            if ($transfer->to_restaurant_id) {
                $user->restaurant_id = $transfer->to_restaurant_id;
            }
            
            if ($transfer->to_branch_id) {
                $user->restaurant_branch_id = $transfer->to_branch_id;
            }
            
            $user->save();

            // Complete the transfer
            $transfer->complete();

            // Log the completion
            Log::info('Staff transfer completed', [
                'transfer_id' => $transfer->id,
                'user_id' => $transfer->user_id,
            ]);

            return $transfer;
        });
    }

    /**
     * Validate transfer request.
     */
    private function validateTransferRequest(array $transferData): void
    {
        $user = User::find($transferData['user_id']);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Check if user is already at the destination
        if (isset($transferData['to_restaurant_id']) && $user->restaurant_id === $transferData['to_restaurant_id']) {
            if (!isset($transferData['to_branch_id']) || $user->restaurant_branch_id === $transferData['to_branch_id']) {
                throw new \Exception('User is already at the specified destination');
            }
        }

        // Validate transfer type
        $transferType = $transferData['transfer_type'];
        switch ($transferType) {
            case 'restaurant_to_restaurant':
                if (!isset($transferData['from_restaurant_id']) || !isset($transferData['to_restaurant_id'])) {
                    throw new \Exception('Restaurant to restaurant transfer requires both from and to restaurant IDs');
                }
                break;

            case 'branch_to_branch':
                if (!isset($transferData['from_branch_id']) || !isset($transferData['to_branch_id'])) {
                    throw new \Exception('Branch to branch transfer requires both from and to branch IDs');
                }
                // Ensure both branches belong to the same restaurant
                $fromBranch = RestaurantBranch::find($transferData['from_branch_id']);
                $toBranch = RestaurantBranch::find($transferData['to_branch_id']);
                if (!$fromBranch || !$toBranch || $fromBranch->restaurant_id !== $toBranch->restaurant_id) {
                    throw new \Exception('Branch to branch transfer must be within the same restaurant');
                }
                break;

            case 'restaurant_to_branch':
                if (!isset($transferData['from_restaurant_id']) || !isset($transferData['to_branch_id'])) {
                    throw new \Exception('Restaurant to branch transfer requires from restaurant and to branch IDs');
                }
                break;

            case 'branch_to_restaurant':
                if (!isset($transferData['from_branch_id']) || !isset($transferData['to_restaurant_id'])) {
                    throw new \Exception('Branch to restaurant transfer requires from branch and to restaurant IDs');
                }
                break;
        }
    }

    /**
     * Check if user can approve a transfer.
     */
    private function canApproveTransfer(StaffTransferHistory $transfer, int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Super admins can approve any transfer
        if ($user->role === 'SUPER_ADMIN') {
            return true;
        }

        // Restaurant owners can approve transfers within their restaurants
        if ($user->role === 'RESTAURANT_OWNER') {
            if ($transfer->from_restaurant_id && $transfer->from_restaurant_id === $user->restaurant_id) {
                return true;
            }
            if ($transfer->to_restaurant_id && $transfer->to_restaurant_id === $user->restaurant_id) {
                return true;
            }
        }

        // Branch managers can approve transfers within their branches only (no cross-restaurant)
        if ($user->role === 'BRANCH_MANAGER') {
            // Check if transfer involves their branch
            $involvesUserBranch = ($transfer->from_branch_id && $transfer->from_branch_id === $user->restaurant_branch_id) ||
                                 ($transfer->to_branch_id && $transfer->to_branch_id === $user->restaurant_branch_id);
            
            // Check if it's within the same restaurant (not cross-restaurant)
            $sameRestaurant = $transfer->from_restaurant_id === $transfer->to_restaurant_id &&
                             $transfer->from_restaurant_id === $user->restaurant_id;
            
            return $involvesUserBranch && $sameRestaurant;
        }

        return false;
    }

    /**
     * Get pending transfers for approval.
     */
    public function getPendingTransfers(User $user): Collection
    {
        $query = StaffTransferHistory::pending();

        // Filter based on user's role and scope
        switch ($user->role) {
            case 'SUPER_ADMIN':
                // Can see all pending transfers
                break;

            case 'RESTAURANT_OWNER':
                // Can see transfers within their restaurants
                $query->where(function ($q) use ($user) {
                    $q->where('from_restaurant_id', $user->restaurant_id)
                      ->orWhere('to_restaurant_id', $user->restaurant_id);
                });
                break;

            case 'BRANCH_MANAGER':
                // Can see transfers within their branch only (no cross-restaurant)
                $query->where(function ($q) use ($user) {
                    $q->where('from_branch_id', $user->restaurant_branch_id)
                      ->orWhere('to_branch_id', $user->restaurant_branch_id);
                })->where(function ($q) use ($user) {
                    // Ensure both from and to restaurants are the same as user's restaurant
                    $q->where('from_restaurant_id', $user->restaurant_id)
                      ->where('to_restaurant_id', $user->restaurant_id);
                });
                break;

            default:
                // Other roles cannot see pending transfers
                return collect();
        }

        return $query->with(['user', 'fromRestaurant', 'toRestaurant', 'fromBranch', 'toBranch', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get transfer statistics.
     */
    public function getTransferStatistics(Restaurant $restaurant = null, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $query = StaffTransferHistory::query();

        if ($restaurant) {
            $query->where(function ($q) use ($restaurant) {
                $q->where('from_restaurant_id', $restaurant->id)
                  ->orWhere('to_restaurant_id', $restaurant->id);
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $transfers = $query->get();

        return [
            'total_transfers' => $transfers->count(),
            'pending_transfers' => $transfers->where('status', 'pending')->count(),
            'approved_transfers' => $transfers->where('status', 'approved')->count(),
            'completed_transfers' => $transfers->where('status', 'completed')->count(),
            'rejected_transfers' => $transfers->where('status', 'rejected')->count(),
            'cancelled_transfers' => $transfers->where('status', 'cancelled')->count(),
            'transfers_by_type' => $transfers->groupBy('transfer_type')->map->count(),
            'average_processing_time' => $transfers->where('status', 'completed')
                ->avg(function ($transfer) {
                    return $transfer->getTransferDuration();
                }),
        ];
    }

    /**
     * Get cross restaurant analytics.
     */
    public function getCrossRestaurantAnalytics(): array
    {
        $restaurants = Restaurant::all();
        $analytics = [];

        foreach ($restaurants as $restaurant) {
            $analytics[$restaurant->id] = [
                'restaurant_name' => $restaurant->name,
                'total_orders' => $restaurant->orders()->count(),
                'total_revenue' => $restaurant->orders()->sum('total_amount'),
                'average_rating' => $restaurant->customerFeedback()->avg('rating'),
                'staff_count' => $restaurant->users()->count(),
                'branch_count' => $restaurant->branches()->count(),
            ];
        }

        return $analytics;
    }

    /**
     * Check cross restaurant access.
     */
    public function checkCrossRestaurantAccess(User $user, Restaurant $targetRestaurant): bool
    {
        if ($user->role === 'SUPER_ADMIN') {
            return true;
        }

        if ($user->role === 'RESTAURANT_OWNER') {
            return $user->restaurant_id === $targetRestaurant->id;
        }

        if ($user->role === 'BRANCH_MANAGER' || $user->role === 'CASHIER') {
            return $user->restaurant_id === $targetRestaurant->id;
        }

        return false;
    }

    /**
     * Get accessible restaurants for a user.
     */
    public function getAccessibleRestaurants(User $user): Collection
    {
        if ($user->role === 'SUPER_ADMIN') {
            return Restaurant::all();
        }

        if ($user->role === 'RESTAURANT_OWNER') {
            return Restaurant::where('id', $user->restaurant_id)->get();
        }

        if ($user->role === 'BRANCH_MANAGER' || $user->role === 'CASHIER') {
            return Restaurant::where('id', $user->restaurant_id)->get();
        }

        return collect();
    }

    /**
     * Get accessible branches for a user
     */
    public function getAccessibleBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return RestaurantBranch::with('restaurant')->get();
        }

        $accessibleBranches = collect();

        // Get branches from user's direct assignments
        if ($user->restaurant_branch_id) {
            $accessibleBranches->push(RestaurantBranch::find($user->restaurant_branch_id));
        }

        // Get branches from user's restaurant assignments
        if ($user->restaurant_id) {
            $restaurantBranches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)->get();
            $accessibleBranches = $accessibleBranches->merge($restaurantBranches);
        }

        // Get branches from user's permissions
        $branchPermissions = $this->getPermissionsForScope($user->role, 'branch');
        foreach ($branchPermissions as $permission) {
            if ($permission->scope_id) {
                $branch = RestaurantBranch::find($permission->scope_id);
                if ($branch && !$accessibleBranches->contains('id', $branch->id)) {
                    $accessibleBranches->push($branch);
                }
            }
        }

        return $accessibleBranches->unique('id')->filter();
    }

    /**
     * Create a new restaurant
     */
    public function createRestaurant(array $data): Restaurant
    {
        // Validate required fields
        $this->validateRestaurantData($data);

        return DB::transaction(function () use ($data) {
            $restaurant = Restaurant::create([
                'name' => $data['name'],
                'slug' => $this->generateRestaurantSlug($data['name']),
                'description' => $data['description'] ?? null,
                'cuisine_type' => $data['cuisine_type'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'business_hours' => $data['business_hours'] ?? [],
                'settings' => $data['settings'] ?? [],
                'status' => $data['status'] ?? 'active',
                'commission_rate' => $data['commission_rate'] ?? 0.00,
                'is_featured' => $data['is_featured'] ?? false,
            ]);

            Log::info('Restaurant created', [
                'restaurant_id' => $restaurant->id,
                'name' => $restaurant->name,
                'created_by' => auth()->id(),
            ]);

            return $restaurant;
        });
    }

    /**
     * Update an existing restaurant
     */
    public function updateRestaurant(Restaurant $restaurant, array $data): Restaurant
    {
        return DB::transaction(function () use ($restaurant, $data) {
            $originalData = $restaurant->toArray();

            $restaurant->update([
                'name' => $data['name'] ?? $restaurant->name,
                'slug' => isset($data['name']) ? $this->generateRestaurantSlug($data['name']) : $restaurant->slug,
                'description' => $data['description'] ?? $restaurant->description,
                'cuisine_type' => $data['cuisine_type'] ?? $restaurant->cuisine_type,
                'phone' => $data['phone'] ?? $restaurant->phone,
                'email' => $data['email'] ?? $restaurant->email,
                'website' => $data['website'] ?? $restaurant->website,
                'logo_url' => $data['logo_url'] ?? $restaurant->logo_url,
                'cover_image_url' => $data['cover_image_url'] ?? $restaurant->cover_image_url,
                'business_hours' => $data['business_hours'] ?? $restaurant->business_hours,
                'settings' => $data['settings'] ?? $restaurant->settings,
                'status' => $data['status'] ?? $restaurant->status,
                'commission_rate' => $data['commission_rate'] ?? $restaurant->commission_rate,
                'is_featured' => $data['is_featured'] ?? $restaurant->is_featured,
            ]);

            Log::info('Restaurant updated', [
                'restaurant_id' => $restaurant->id,
                'name' => $restaurant->name,
                'updated_by' => auth()->id(),
                'changes' => 'Restaurant data updated',
            ]);

            return $restaurant->fresh();
        });
    }

    /**
     * Delete a restaurant
     */
    public function deleteRestaurant(Restaurant $restaurant): bool
    {
        return DB::transaction(function () use ($restaurant) {
            // Check if restaurant has active orders
            $activeOrders = $restaurant->orders()->whereIn('status', ['pending', 'confirmed', 'preparing'])->count();
            if ($activeOrders > 0) {
                throw new \Exception("Cannot delete restaurant with {$activeOrders} active orders");
            }

            // Soft delete or mark as inactive
            $restaurant->update(['status' => 'inactive']);

            Log::info('Restaurant deleted', [
                'restaurant_id' => $restaurant->id,
                'name' => $restaurant->name,
                'deleted_by' => auth()->id(),
            ]);

            return true;
        });
    }

    /**
     * Get restaurant details by ID
     */
    public function getRestaurantDetails(int $restaurantId): Restaurant
    {
        $restaurant = Restaurant::with(['branches', 'menuCategories', 'loyaltyPrograms'])
            ->findOrFail($restaurantId);

        return $restaurant;
    }

    /**
     * Create a new branch for a restaurant
     */
    public function createBranch(int $restaurantId, array $data): RestaurantBranch
    {
        // Validate restaurant exists
        $restaurant = Restaurant::findOrFail($restaurantId);

        // Validate required fields
        $this->validateBranchData($data);

        return DB::transaction(function () use ($restaurant, $data) {
            $branch = RestaurantBranch::create([
                'restaurant_id' => $restaurant->id,
                'name' => $data['name'],
                'slug' => $this->generateBranchSlug($restaurant->id, $data['name']),
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'phone' => $data['phone'] ?? null,
                'manager_name' => $data['manager_name'] ?? null,
                'manager_phone' => $data['manager_phone'] ?? null,
                'operating_hours' => $data['operating_hours'] ?? [],
                'delivery_zones' => $data['delivery_zones'] ?? [],
                'delivery_fee' => $data['delivery_fee'] ?? 0.00,
                'minimum_order_amount' => $data['minimum_order_amount'] ?? 0.00,
                'estimated_delivery_time' => $data['estimated_delivery_time'] ?? 30,
                'status' => $data['status'] ?? 'active',
                'accepts_online_orders' => $data['accepts_online_orders'] ?? true,
                'accepts_delivery' => $data['accepts_delivery'] ?? true,
                'accepts_pickup' => $data['accepts_pickup'] ?? true,
                'settings' => $data['settings'] ?? [],
            ]);

            Log::info('Branch created', [
                'branch_id' => $branch->id,
                'restaurant_id' => $restaurant->id,
                'name' => $branch->name,
                'created_by' => auth()->id(),
            ]);

            return $branch;
        });
    }

    /**
     * Update an existing branch
     */
    public function updateBranch(RestaurantBranch $branch, array $data): RestaurantBranch
    {
        return DB::transaction(function () use ($branch, $data) {
            $originalData = $branch->toArray();

            $branch->update([
                'name' => $data['name'] ?? $branch->name,
                'slug' => isset($data['name']) ? $this->generateBranchSlug($branch->restaurant_id, $data['name']) : $branch->slug,
                'address' => $data['address'] ?? $branch->address,
                'city' => $data['city'] ?? $branch->city,
                'state' => $data['state'] ?? $branch->state,
                'postal_code' => $data['postal_code'] ?? $branch->postal_code,
                'country' => $data['country'] ?? $branch->country,
                'latitude' => $data['latitude'] ?? $branch->latitude,
                'longitude' => $data['longitude'] ?? $branch->longitude,
                'phone' => $data['phone'] ?? $branch->phone,
                'manager_name' => $data['manager_name'] ?? $branch->manager_name,
                'manager_phone' => $data['manager_phone'] ?? $branch->manager_phone,
                'operating_hours' => $data['operating_hours'] ?? $branch->operating_hours,
                'delivery_zones' => $data['delivery_zones'] ?? $branch->delivery_zones,
                'delivery_fee' => $data['delivery_fee'] ?? $branch->delivery_fee,
                'minimum_order_amount' => $data['minimum_order_amount'] ?? $branch->minimum_order_amount,
                'estimated_delivery_time' => $data['estimated_delivery_time'] ?? $branch->estimated_delivery_time,
                'status' => $data['status'] ?? $branch->status,
                'accepts_online_orders' => $data['accepts_online_orders'] ?? $branch->accepts_online_orders,
                'accepts_delivery' => $data['accepts_delivery'] ?? $branch->accepts_delivery,
                'accepts_pickup' => $data['accepts_pickup'] ?? $branch->accepts_pickup,
                'settings' => $data['settings'] ?? $branch->settings,
            ]);

            Log::info('Branch updated', [
                'branch_id' => $branch->id,
                'restaurant_id' => $branch->restaurant_id,
                'name' => $branch->name,
                'updated_by' => auth()->id(),
                'changes' => 'Branch data updated',
            ]);

            return $branch->fresh();
        });
    }

    /**
     * Delete a branch
     */
    public function deleteBranch(RestaurantBranch $branch): bool
    {
        return DB::transaction(function () use ($branch) {
            // Check if branch has active orders
            $activeOrders = $branch->orders()->whereIn('status', ['pending', 'confirmed', 'preparing'])->count();
            if ($activeOrders > 0) {
                throw new \Exception("Cannot delete branch with {$activeOrders} active orders");
            }

            // Check if branch has assigned staff
            $assignedStaff = $branch->users()->count();
            if ($assignedStaff > 0) {
                throw new \Exception("Cannot delete branch with {$assignedStaff} assigned staff members");
            }

            // Soft delete or mark as inactive
            $branch->update(['status' => 'inactive']);

            Log::info('Branch deleted', [
                'branch_id' => $branch->id,
                'restaurant_id' => $branch->restaurant_id,
                'name' => $branch->name,
                'deleted_by' => auth()->id(),
            ]);

            return true;
        });
    }

    /**
     * Assign a user to a restaurant
     */
    public function assignUserToRestaurant(User $user, int $restaurantId): void
    {
        DB::transaction(function () use ($user, $restaurantId) {
            // Validate restaurant exists
            $restaurant = Restaurant::findOrFail($restaurantId);

            // Remove user from any existing branch assignment
            $user->update([
                'restaurant_id' => $restaurantId,
                'restaurant_branch_id' => null,
            ]);

            Log::info('User assigned to restaurant', [
                'user_id' => $user->id,
                'restaurant_id' => $restaurantId,
                'assigned_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Assign a user to a branch
     */
    public function assignUserToBranch(User $user, int $branchId): void
    {
        DB::transaction(function () use ($user, $branchId) {
            // Validate branch exists
            $branch = RestaurantBranch::findOrFail($branchId);

            // Assign user to both restaurant and branch
            $user->update([
                'restaurant_id' => $branch->restaurant_id,
                'restaurant_branch_id' => $branchId,
            ]);

            Log::info('User assigned to branch', [
                'user_id' => $user->id,
                'restaurant_id' => $branch->restaurant_id,
                'branch_id' => $branchId,
                'assigned_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Remove a user from a restaurant
     */
    public function removeUserFromRestaurant(User $user, int $restaurantId): void
    {
        DB::transaction(function () use ($user, $restaurantId) {
            // Validate user is assigned to this restaurant
            if ($user->restaurant_id !== $restaurantId) {
                throw new \Exception('User is not assigned to this restaurant');
            }

            // Remove user from restaurant and branch
            $user->update([
                'restaurant_id' => null,
                'restaurant_branch_id' => null,
            ]);

            Log::info('User removed from restaurant', [
                'user_id' => $user->id,
                'restaurant_id' => $restaurantId,
                'removed_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Validate restaurant data
     */
    private function validateRestaurantData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Restaurant name is required');
        }

        // Check for duplicate name
        if (Restaurant::where('name', $data['name'])->exists()) {
            throw new \InvalidArgumentException('Restaurant with this name already exists');
        }
    }

    /**
     * Validate branch data
     */
    private function validateBranchData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Branch name is required');
        }

        if (empty($data['address'])) {
            throw new \InvalidArgumentException('Branch address is required');
        }

        if (empty($data['city'])) {
            throw new \InvalidArgumentException('Branch city is required');
        }
    }

    /**
     * Generate unique restaurant slug
     */
    private function generateRestaurantSlug(string $name): string
    {
        $baseSlug = \Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Restaurant::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate unique branch slug
     */
    private function generateBranchSlug(int $restaurantId, string $name): string
    {
        $baseSlug = \Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (RestaurantBranch::where('restaurant_id', $restaurantId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
} 