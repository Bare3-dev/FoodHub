<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'in:SUPER_ADMIN,RESTAURANT_OWNER,BRANCH_MANAGER,CASHIER,KITCHEN_STAFF,DELIVERY_MANAGER,CUSTOMER_SERVICE'],
        ]);

        // Role validation: Only super admins can create super admins
        $requestedRole = $request->role;
        if ($requestedRole === 'SUPER_ADMIN') {
            // Check if there's an authenticated user and they're a super admin
            if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
                return response()->json([
                    'message' => 'Only super administrators can create super admin accounts.',
                    'errors' => [
                        'role' => ['Insufficient privileges to create this role.']
                    ]
                ], 403);
            }
        }

        // Role validation: Restaurant owners can only be created by super admins
        if ($requestedRole === 'RESTAURANT_OWNER') {
            if (!Auth::check() || !Auth::user()->isSuperAdmin()) {
                return response()->json([
                    'message' => 'Only super administrators can create restaurant owner accounts.',
                    'errors' => [
                        'role' => ['Insufficient privileges to create this role.']
                    ]
                ], 403);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
            'role' => $request->role,
            'permissions' => [], // Default empty permissions
            'status' => 'active', // Default active status
            'is_email_verified' => false, // Default to false, will be verified later
        ]);

        event(new Registered($user));

        Auth::login($user);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    /**
     * Handle staff registration (for authenticated users with proper permissions)
     */
    public function storeStaff(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:BRANCH_MANAGER,CASHIER,KITCHEN_STAFF,DELIVERY_MANAGER,CUSTOMER_SERVICE'],
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'restaurant_branch_id' => ['nullable', 'exists:restaurant_branches,id'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user = Auth::user();
        $requestedRole = $request->role;

        // Role hierarchy validation
        if (!$this->canCreateRole($user, $requestedRole)) {
            return response()->json([
                'message' => 'You do not have permission to create users with this role.',
                'errors' => [
                    'role' => ['Insufficient privileges to create this role.']
                ]
            ], 403);
        }

        // Validate restaurant/branch assignments based on user's role
        if (!$this->validateRestaurantAssignment($user, $request->restaurant_id, $request->restaurant_branch_id)) {
            return response()->json([
                'message' => 'Invalid restaurant or branch assignment.',
                'errors' => [
                    'restaurant_id' => ['You can only assign users to your own restaurant/branch.']
                ]
            ], 403);
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $requestedRole,
            'permissions' => $request->permissions ?? [],
            'status' => 'active',
            'is_email_verified' => false,
        ];

        // Set restaurant/branch based on user's role and permissions
        if ($user->isSuperAdmin()) {
            $userData['restaurant_id'] = $request->restaurant_id;
            $userData['restaurant_branch_id'] = $request->restaurant_branch_id;
        } elseif ($user->hasRole('RESTAURANT_OWNER')) {
            $userData['restaurant_id'] = $user->restaurant_id;
            $userData['restaurant_branch_id'] = $request->restaurant_branch_id;
        } elseif ($user->hasRole('BRANCH_MANAGER')) {
            $userData['restaurant_id'] = $user->restaurant_id;
            $userData['restaurant_branch_id'] = $user->restaurant_branch_id;
        }

        $newUser = User::create($userData);

        return response()->json([
            'message' => 'Staff member created successfully.',
            'user' => $newUser
        ], 201);
    }

    /**
     * Handle restaurant owner registration (super admin only)
     */
    public function storeRestaurantOwner(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'min:8'],
            'restaurant_id' => ['required', 'exists:restaurants,id'],
        ]);

        $user = Auth::user();

        // Only super admins can create restaurant owners
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only super administrators can create restaurant owner accounts.',
            ], 403);
        }

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'RESTAURANT_OWNER',
            'restaurant_id' => $request->restaurant_id,
            'permissions' => [],
            'status' => 'active',
            'is_email_verified' => false,
        ]);

        return response()->json([
            'message' => 'Restaurant owner created successfully.',
            'user' => $newUser
        ], 201);
    }

    /**
     * Handle super admin registration (super admin only)
     */
    public function storeSuperAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = Auth::user();

        // Only super admins can create other super admins
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only super administrators can create super admin accounts.',
            ], 403);
        }

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'SUPER_ADMIN',
            'permissions' => [],
            'status' => 'active',
            'is_email_verified' => false,
        ]);

        return response()->json([
            'message' => 'Super admin created successfully.',
            'user' => $newUser
        ], 201);
    }

    /**
     * Check if the current user can create a user with the specified role
     */
    private function canCreateRole(User $user, string $role): bool
    {
        // Super admins can create any role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can create branch managers and below
        if ($user->hasRole('RESTAURANT_OWNER')) {
            return in_array($role, ['BRANCH_MANAGER', 'CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE']);
        }

        // Branch managers can create staff roles only
        if ($user->hasRole('BRANCH_MANAGER')) {
            return in_array($role, ['CASHIER', 'KITCHEN_STAFF', 'DELIVERY_MANAGER', 'CUSTOMER_SERVICE']);
        }

        return false;
    }

    /**
     * Validate restaurant/branch assignment based on user's role
     */
    private function validateRestaurantAssignment(User $user, ?int $restaurantId, ?int $branchId): bool
    {
        // Super admins can assign to any restaurant/branch
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Restaurant owners can only assign to their own restaurant
        if ($user->hasRole('RESTAURANT_OWNER')) {
            if ($restaurantId && $restaurantId !== $user->restaurant_id) {
                return false;
            }
            // Branch must belong to their restaurant
            if ($branchId) {
                $branch = \App\Models\RestaurantBranch::find($branchId);
                return $branch && $branch->restaurant_id === $user->restaurant_id;
            }
            return true;
        }

        // Branch managers can only assign to their own branch
        if ($user->hasRole('BRANCH_MANAGER')) {
            if ($branchId && $branchId !== $user->restaurant_branch_id) {
                return false;
            }
            return true;
        }

        return false;
    }
}
