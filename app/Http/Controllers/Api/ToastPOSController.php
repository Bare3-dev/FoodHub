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

class ToastPOSController extends Controller
{
    public function __construct(
        private readonly POSIntegrationService $posIntegrationService
    ) {}

    /**
     * Send FoodHub orders to Toast POS.
     */
    public function syncOrder(Order $order): Response
    {
        try {
            $this->authorize('syncOrder', $order);

            $result = $this->posIntegrationService->createPOSOrder($order, 'toast');

            return response([
                'success' => true,
                'message' => 'Order synced to Toast POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Toast POS order sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync order to Toast POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync menu items and prices with Toast POS.
     */
    public function syncMenu(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncMenu', $restaurant);

            $result = $this->posIntegrationService->syncMenuItems($restaurant, 'toast', 'both');

            return response([
                'success' => true,
                'message' => 'Menu synced with Toast POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Toast POS menu sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync menu with Toast POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync stock levels with Toast POS.
     */
    public function syncInventory(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncInventory', $restaurant);

            $result = $this->posIntegrationService->syncInventoryLevels($restaurant, 'toast');

            return response([
                'success' => true,
                'message' => 'Inventory synced with Toast POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Toast POS inventory sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync inventory with Toast POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Toast POS status updates.
     */
    public function handlePOSWebhook(Request $request): Response
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Toast-Signature');

            if (!$signature) {
                return response([
                    'success' => false,
                    'message' => 'Missing Toast signature'
                ], 400);
            }

            // Process the webhook through the WebhookService
            app(\App\Services\WebhookService::class)->handleToastWebhook($payload, $signature);

            return response([
                'success' => true,
                'message' => 'Toast POS webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Toast POS webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to process Toast POS webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Toast POS connectivity.
     */
    public function validatePOSConnection(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('validatePOSConnection', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'toast')
                ->where('is_active', true)
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No active Toast POS integration found',
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
                    'message' => 'Toast POS configuration incomplete',
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
                    'message' => 'Toast POS connection validated successfully',
                    'connected' => true,
                    'last_sync' => $integration->last_sync_at
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Toast POS connection test failed',
                    'connected' => false,
                    'error' => $response->body()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Toast POS connection validation failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to validate Toast POS connection',
                'connected' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Toast POS integration status.
     */
    public function getIntegrationStatus(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('viewIntegrationStatus', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'toast')
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No Toast POS integration found',
                    'integrated' => false
                ], 404);
            }

            $syncLogs = $integration->syncLogs()
                ->latest()
                ->take(10)
                ->get();

            return response([
                'success' => true,
                'message' => 'Toast POS integration status retrieved',
                'data' => [
                    'integrated' => true,
                    'is_active' => $integration->is_active,
                    'last_sync_at' => $integration->last_sync_at,
                    'configuration' => $integration->configuration,
                    'recent_sync_logs' => $syncLogs
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get Toast POS integration status', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to get Toast POS integration status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 