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

class LocalPOSController extends Controller
{
    public function __construct(
        private readonly POSIntegrationService $posIntegrationService
    ) {}

    /**
     * Send FoodHub orders to Local POS.
     */
    public function syncOrder(Order $order): Response
    {
        try {
            $this->authorize('syncOrder', $order);

            $result = $this->posIntegrationService->createPOSOrder($order, 'local');

            return response([
                'success' => true,
                'message' => 'Order synced to Local POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Local POS order sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync order to Local POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync menu items and prices with Local POS.
     */
    public function syncMenu(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncMenu', $restaurant);

            $result = $this->posIntegrationService->syncMenuItems($restaurant, 'local', 'both');

            return response([
                'success' => true,
                'message' => 'Menu synced with Local POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Local POS menu sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync menu with Local POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync stock levels with Local POS.
     */
    public function syncInventory(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('syncInventory', $restaurant);

            $result = $this->posIntegrationService->syncInventoryLevels($restaurant, 'local');

            return response([
                'success' => true,
                'message' => 'Inventory synced with Local POS successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Local POS inventory sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to sync inventory with Local POS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Local POS status updates.
     */
    public function handlePOSWebhook(string $posId, Request $request): Response
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Local-POS-Signature');

            if (!$signature) {
                return response([
                    'success' => false,
                    'message' => 'Missing Local POS signature'
                ], 400);
            }

            // Process the webhook through the WebhookService
            app(\App\Services\WebhookService::class)->handleLocalPOSWebhook($posId, $payload, $signature);

            return response([
                'success' => true,
                'message' => 'Local POS webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Local POS webhook processing failed', [
                'pos_id' => $posId,
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to process Local POS webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Local POS connectivity.
     */
    public function validatePOSConnection(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('validatePOSConnection', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'local')
                ->where('is_active', true)
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No active Local POS integration found',
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
                    'message' => 'Local POS configuration incomplete',
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
                    'message' => 'Local POS connection validated successfully',
                    'connected' => true,
                    'last_sync' => $integration->last_sync_at
                ], 200);
            } else {
                return response([
                    'success' => false,
                    'message' => 'Local POS connection test failed',
                    'connected' => false,
                    'error' => $response->body()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Local POS connection validation failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to validate Local POS connection',
                'connected' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Local POS integration status.
     */
    public function getIntegrationStatus(Restaurant $restaurant): Response
    {
        try {
            $this->authorize('viewIntegrationStatus', $restaurant);

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', 'local')
                ->first();

            if (!$integration) {
                return response([
                    'success' => false,
                    'message' => 'No Local POS integration found',
                    'integrated' => false
                ], 404);
            }

            $syncLogs = $integration->syncLogs()
                ->latest()
                ->take(10)
                ->get();

            return response([
                'success' => true,
                'message' => 'Local POS integration status retrieved',
                'data' => [
                    'integrated' => true,
                    'is_active' => $integration->is_active,
                    'last_sync_at' => $integration->last_sync_at,
                    'configuration' => $integration->configuration,
                    'recent_sync_logs' => $syncLogs
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get Local POS integration status', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response([
                'success' => false,
                'message' => 'Failed to get Local POS integration status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 