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
    public function getBranchConfig(Request $request, RestaurantBranch $branch): JsonResponse
    {
        try {
            // Check if user can view restaurant configs
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $branch->restaurant_id]);
            $this->authorize('view', $dummyConfig);

            $key = $request->query('key');
            $config = $this->configurationService->getBranchConfig($branch, $key);

            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Branch configuration retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get branch config', [
                'branch_id' => $branch->id,
                'key' => $key ?? 'all',
                'error' => $e->getMessage(),
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
    public function updateOperatingHours(UpdateOperatingHoursRequest $request, RestaurantBranch $branch): JsonResponse
    {
        try {
            // Check if user can update restaurant configs
            // Create a dummy config instance for authorization check
            $dummyConfig = new RestaurantConfig(['restaurant_id' => $branch->restaurant_id]);
            $this->authorize('update', $dummyConfig);

            $this->configurationService->updateOperatingHours($branch, $request->validated()['operating_hours']);

            return response()->json([
                'success' => true,
                'message' => 'Operating hours updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update operating hours', [
                'branch_id' => $branch->id,
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