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

final class MultiRestaurantService
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

        // Branch managers can approve transfers within their branches
        if ($user->role === 'BRANCH_MANAGER') {
            if ($transfer->from_branch_id && $transfer->from_branch_id === $user->restaurant_branch_id) {
                return true;
            }
            if ($transfer->to_branch_id && $transfer->to_branch_id === $user->restaurant_branch_id) {
                return true;
            }
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
                // Can see transfers within their branch
                $query->where(function ($q) use ($user) {
                    $q->where('from_branch_id', $user->restaurant_branch_id)
                      ->orWhere('to_branch_id', $user->restaurant_branch_id);
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

        return collect();
    }

    /**
     * Get accessible branches for a user.
     */
    public function getAccessibleBranches(User $user): Collection
    {
        if ($user->role === 'SUPER_ADMIN') {
            return RestaurantBranch::all();
        }

        if ($user->role === 'RESTAURANT_OWNER') {
            return RestaurantBranch::where('restaurant_id', $user->restaurant_id)->get();
        }

        if ($user->role === 'BRANCH_MANAGER') {
            return RestaurantBranch::where('id', $user->restaurant_branch_id)->get();
        }

        return collect();
    }
} 