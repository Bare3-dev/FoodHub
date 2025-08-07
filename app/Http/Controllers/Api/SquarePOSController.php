<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\PosIntegration;
use App\Services\POSIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SquarePOSController extends Controller
{
    public function __construct(
        private readonly POSIntegrationService $posIntegrationService
    ) {}

    /**
     * Send FoodHub orders to Square POS.
     */
    public function syncOrder(Order $order): Response
    {
        try {
            $this->authorize('syncOrder', $order);

            $result = $this->posIntegrationService->createPOSOrder($order, 'square');

            if ($result) {
                return response([
                    'success' => true,
                    'message' => 'Order synced successfully'
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Failed to sync order'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Square POS order sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync order to Square POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync menu items and prices with Square POS.
     */
    public function syncMenu(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncMenu', $restaurant);

            $result = $this->posIntegrationService->syncMenuItems($restaurant, 'square', 'both');

            if ($result) {
                return response([
                    'success' => true,
                    'message' => 'Menu synced successfully'
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Failed to sync menu'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Square POS menu sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync menu with Square POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync stock levels with Square POS.
     */
    public function syncInventory(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncInventory', $restaurant);

            $result = $this->posIntegrationService->syncInventoryLevels($restaurant, 'square');

            if ($result) {
                return response([
                    'success' => true,
                    'message' => 'Inventory synced successfully'
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Failed to sync inventory'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Square POS inventory sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync inventory with Square POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Square POS status updates.
     */
    public function handlePOSWebhook(Request $request): Response
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Square-Signature');

            if (!$signature) {
                return response([
                    'success' => false,
                    'message' => 'Missing Square signature'
                ], 400);
            }

            // Process the webhook through the WebhookService
            app(\App\Services\WebhookService::class)->handleSquareWebhook($payload, $signature);

            return response([
                'success' => true,
                'message' => 'Square POS webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Square POS webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to process Square POS webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Square POS connectivity.
     */
    public function validatePOSConnection(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('validatePOSConnection', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'square')
                ->where('is_active', true)
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No active Square POS integration found',
                    'connected' => false
                ], 404);
            }

            // Test connection by making a simple API call
            $config = $integration->configuration;
            $baseUrl = $config['api_url'] ?? '';
            $apiKey = $config['api_key'] ?? '';

            if (!$baseUrl || !$apiKey) {
                return response([
                    'success' => false,
                    'message' => 'Square POS configuration incomplete',
                    'connected' => false
                ], 400);
            }

            // Make a test API call to verify connectivity
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json'
            ])->get("{$baseUrl}/test");

            if ($response->successful()) {
                return response([
                    'success' => true,
                    'message' => 'Square POS connection validated successfully',
                    'connected' => true,
                    'last_sync' => $integration->last_sync_at
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Square POS connection test failed',
                    'connected' => false,
                    'error' => $response->body()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Square POS connection validation failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to validate Square POS connection',
                'connected' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Square POS integration status.
     */
    public function getIntegrationStatus(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('viewPOSStatus', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'square')
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No Square POS integration found',
                    'data' => [
                        'pos_type' => 'square',
                        'integrated' => false,
                        'is_active' => false,
                        'last_sync_at' => null
                    ]
                ], 404);
            }

            return response([
                'success' => true,
                'message' => 'Square POS integration status retrieved',
                'data' => [
                    'pos_type' => $integration->pos_type,
                    'integrated' => true,
                    'is_active' => $integration->is_active,
                    'last_sync_at' => $integration->last_sync_at,
                    'created_at' => $integration->created_at
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get Square POS integration status', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to get Square POS integration status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 