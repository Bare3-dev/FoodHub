<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\StoreRestaurantConfigRequest;
use App\Http\Requests\Configuration\UpdateOperatingHoursRequest;
use App\Http\Requests\Configuration\ConfigureLoyaltyProgramRequest;
use App\Http\Resources\Api\ConfigurationResource;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantConfig;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ConfigurationController extends Controller
{
    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {}

    /**
     * Get restaurant configuration
     */
    public function getRestaurantConfig(Request $request, Restaurant $restaurant): JsonResponse
    {
        try {
            // Check if user can view restaurant configs
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $restaurant->id]);
            $this->authorize('view', $dummyConfig);

            $key = $request->query('key');
            $config = $this->configurationService->getRestaurantConfig($restaurant, $key);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Restaurant configuration retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get restaurant config', [
                'restaurant_id' => $restaurant->id,
                'key' => $key ?? 'all',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve restaurant configuration',
            ], 500);
        }
    }

    /**
     * Set restaurant configuration
     */
    public function setRestaurantConfig(StoreRestaurantConfigRequest $request, Restaurant $restaurant): JsonResponse
    {
        try {
            // Check if user can create restaurant configs
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $restaurant->id]);
            $this->authorize('create', $dummyConfig);

            $validated = $request->validated();
            $this->configurationService->setRestaurantConfig(
                $restaurant,
                $validated['key'],
                $validated['value']
            );

            return response()->json([
                'success' => true,
                'message' => 'Restaurant configuration set successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set restaurant config', [
                'restaurant_id' => $restaurant->id,
                'key' => $validated['key'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set restaurant configuration',
            ], 500);
        }
    }

    /**
     * Get branch configuration
     */
    public function getBranchConfig(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        try {
            // Debug logging
            \Log::info('getBranchConfig called', [
                'branch_id' => $restaurantBranch->id,
                'branch_restaurant_id' => $restaurantBranch->restaurant_id,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->role,
                'user_permissions' => auth()->user()->permissions,
            ]);

            // Ensure branch has restaurant relationship loaded
            if (!$restaurantBranch->relationLoaded('restaurant')) {
                $restaurantBranch->load('restaurant');
            }
            
            // Verify branch has a restaurant
            if (!$restaurantBranch->restaurant_id || !$restaurantBranch->restaurant) {
                \Log::error('Branch not associated with restaurant', [
                    'branch_id' => $restaurantBranch->id,
                    'branch_restaurant_id' => $restaurantBranch->restaurant_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not associated with a restaurant',
                ], 400);
            }
            
            // Check if user can view restaurant configs
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $restaurantBranch->restaurant_id]);
            
            \Log::info('About to authorize', [
                'dummy_config_restaurant_id' => $dummyConfig->restaurant_id,
                'user_restaurant_id' => auth()->user()->restaurant_id,
                'user_has_permission' => auth()->user()->hasPermission('view restaurant configs'),
            ]);
            
            $this->authorize('view', $dummyConfig);

            $key = $request->query('key');
            $config = $this->configurationService->getBranchConfig($restaurantBranch, $key);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Branch configuration retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get branch config', [
                'branch_id' => $restaurantBranch->id,
                'branch_restaurant_id' => $restaurantBranch->restaurant_id,
                'key' => $request->query('key') ?? 'all',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch configuration',
            ], 500);
        }
    }

    /**
     * Update branch operating hours
     */
    public function updateOperatingHours(UpdateOperatingHoursRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        try {
            // Ensure branch has restaurant relationship loaded
            if (!$restaurantBranch->relationLoaded('restaurant')) {
                $restaurantBranch->load('restaurant');
            }
            
            // Verify branch has a restaurant
            if (!$restaurantBranch->restaurant_id || !$restaurantBranch->restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch not associated with a restaurant',
                ], 400);
            }
            
            // Check if user can update restaurant configs
            // Create a dummy config instance for authorization check
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $restaurantBranch->restaurant_id]);
            $this->authorize('update', $dummyConfig);

            $this->configurationService->updateOperatingHours($restaurantBranch, $request->validated()['operating_hours']);

            return response()->json([
                'success' => true,
                'message' => 'Operating hours updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update operating hours', [
                'branch_id' => $restaurantBranch->id,
                'branch_restaurant_id' => $restaurantBranch->restaurant_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update operating hours',
            ], 500);
        }
    }

    /**
     * Configure loyalty program settings
     */
    public function configureLoyaltyProgram(ConfigureLoyaltyProgramRequest $request, Restaurant $restaurant): JsonResponse
    {
        try {
            // Check if user can update restaurant configs
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $restaurant->id]);
            $this->authorize('update', $dummyConfig);

            $this->configurationService->configureLoyaltyProgram($restaurant, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loyalty program configured successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to configure loyalty program', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to configure loyalty program',
            ], 500);
        }
    }
} 