<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\PosIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class POSIntegrationController extends Controller
{
    /**
     * Integrate restaurant with POS system.
     */
    public function integrate(string $type, Request $request): Response
    {
        try {
            $restaurantId = $request->input('restaurant_id');
            $restaurant = Restaurant::findOrFail($restaurantId);
            
            $this->authorize('integratePOS', $restaurant);

            // Validate POS type
            if (!in_array($type, ['square', 'toast', 'local'])) {
                return response([
                    'success' => false,
                    'message' => 'Invalid POS type. Must be square, toast, or local.'
                ], 400);
            }

            // Check if integration already exists
            $existingIntegration = PosIntegration::where('restaurant_id', $restaurantId)
                ->where('pos_type', $type)
                ->first();

            if ($existingIntegration) {
                return response([
                    'success' => false,
                    'message' => "Restaurant already has {$type} POS integration"
                ], 409);
            }

            // Validate configuration
            $configuration = $request->input('configuration', []);
            $this->validateConfiguration($type, $configuration);

            // Create integration
            $integration = PosIntegration::create([
                'restaurant_id' => $restaurantId,
                'pos_type' => $type,
                'configuration' => $configuration,
                'is_active' => true,
                'last_sync_at' => null
            ]);

            Log::info('POS integration created', [
                'restaurant_id' => $restaurantId,
                'pos_type' => $type,
                'integration_id' => $integration->id
            ]);

            return response([
                'success' => true,
                'message' => "Successfully integrated with {$type} POS",
                'data' => [
                    'id' => $integration->id,
                    'restaurant_id' => $integration->restaurant_id,
                    'pos_type' => $integration->pos_type,
                    'configuration' => $integration->configuration,
                    'is_active' => $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('POS integration failed', [
                'type' => $type,
                'restaurant_id' => $request->input('restaurant_id'),
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to integrate with POS system',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get POS integration status for restaurant.
     */
    public function getStatus(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('viewPOSStatus', $restaurant);

            $integrations = PosIntegration::where('restaurant_id', $restaurant->id)
                ->with(['syncLogs' => function ($query) {
                    $query->latest()->take(5);
                }])
                ->get();

            $statusData = [];
            foreach (['square', 'toast', 'local'] as $posType) {
                $integration = $integrations->where('pos_type', $posType)->first();
                
                if ($integration) {
                    $statusData[] = [
                        'id' => $integration->id,
                        'pos_type' => $integration->pos_type,
                        'is_active' => $integration->is_active,
                        'last_sync_at' => $integration->last_sync_at,
                        'created_at' => $integration->created_at
                    ];
                }
            }

            return response([
                'success' => true,
                'message' => 'POS integration status retrieved',
                'data' => [
                    'restaurant_id' => $restaurant->id,
                    'integrations' => $statusData
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get POS integration status', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to get POS integration status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update POS integration configuration.
     */
    public function updateConfiguration(PosIntegration $integration, Request $request): Response
    {
        try {
            $this->authorize('update', $integration);

            $configuration = $request->input('configuration', []);
            $this->validateConfiguration($integration->pos_type, $configuration);

            $integration->update([
                'configuration' => $configuration
            ]);

            Log::info('POS integration configuration updated', [
                'integration_id' => $integration->id,
                'pos_type' => $integration->pos_type
            ]);

            return response([
                'success' => true,
                'message' => 'POS integration configuration updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update POS integration configuration', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to update POS integration configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle POS integration status.
     */
    public function toggleStatus(PosIntegration $integration): Response
    {
        try {
            $this->authorize('update', $integration);

            $integration->update([
                'is_active' => !$integration->is_active
            ]);

            $status = $integration->is_active ? 'activated' : 'deactivated';

            Log::info('POS integration status toggled', [
                'integration_id' => $integration->id,
                'pos_type' => $integration->pos_type,
                'new_status' => $status
            ]);

            return response([
                'success' => true,
                'message' => "POS integration {$status} successfully"
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to toggle POS integration status', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to toggle POS integration status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete POS integration.
     */
    public function delete(PosIntegration $integration): Response
    {
        try {
            $this->authorize('delete', $integration);

            $integration->delete();

            Log::info('POS integration deleted', [
                'integration_id' => $integration->id,
                'pos_type' => $integration->pos_type
            ]);

            return response([
                'success' => true,
                'message' => 'POS integration deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to delete POS integration', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to delete POS integration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync logs for POS integration.
     */
    public function getSyncLogs(PosIntegration $integration, Request $request): Response
    {
        try {
            $this->authorize('viewPOSIntegration', $integration);

            $perPage = min($request->input('per_page', 15), 100);
            $syncType = $request->input('sync_type');
            $status = $request->input('status');

            $query = $integration->syncLogs();

            if ($syncType) {
                $query->where('sync_type', $syncType);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $logs = $query->latest()->paginate($perPage);

            return response([
                'success' => true,
                'message' => 'POS sync logs retrieved',
                'data' => [
                    'logs' => $logs->items(),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get POS sync logs', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to get POS sync logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate POS configuration based on type.
     */
    private function validateConfiguration(string $posType, array $configuration): void
    {
        $requiredFields = [];

        switch ($posType) {
            case 'square':
                $requiredFields = ['api_url', 'api_key', 'location_id'];
                break;
            case 'toast':
                $requiredFields = ['api_url', 'api_key', 'restaurant_id'];
                break;
            case 'local':
                $requiredFields = ['api_url', 'api_key', 'pos_id'];
                break;
            default:
                throw new \InvalidArgumentException("Invalid POS type: {$posType}");
        }

        foreach ($requiredFields as $field) {
            if (empty($configuration[$field])) {
                throw new \InvalidArgumentException("Missing required configuration field: {$field}");
            }
        }
    }
} 