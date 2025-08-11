<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\PosIntegration;
use App\Services\POSIntegrationService;
use App\Services\SecurityLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Square POS Integration Controller
 * 
 * Handles integration with Square POS system including:
 * - Order synchronization
 * - Menu management
 * - Inventory tracking
 * - Webhook processing
 */
class SquarePOSController extends Controller
{
    public function __construct(
        private readonly POSIntegrationService $posService,
        private readonly SecurityLoggingService $securityService
    ) {}

    /**
     * Sync order to Square POS
     */
    public function syncOrder(Order $order): JsonResponse
    {
        try {
            $restaurant = $order->restaurant;

            // Verify user has access to this restaurant
            if (!$this->canAccessRestaurant($restaurant->id)) {
                $this->securityService->logSecurityIncident(
                    'authorization_failure',
                    'medium',
                    'Unauthorized access attempt to restaurant',
                    ['restaurant_id' => $restaurant->id, 'user_id' => auth()->id()]
                );

                return response()->json([
                    'error' => 'Authorization Failed',
                    'message' => 'You do not have permission to access this restaurant.'
                ], 403);
            }

            $result = $this->posService->createPOSOrder($order, POSIntegrationService::POS_SQUARE);

            return response()->json([
                'success' => true,
                'message' => 'Order synced successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Square POS order sync failed', [
                'order_id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Sync Failed',
                'message' => 'Failed to sync order to Square POS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync menu items from Square POS
     */
    public function syncMenu(Restaurant $restaurant): JsonResponse
    {
        try {
            // Verify user has access to this restaurant
            if (!$this->canAccessRestaurant($restaurant->id)) {
                $this->securityService->logSecurityIncident(
                    'authorization_failure',
                    'medium',
                    'Unauthorized access attempt to restaurant',
                    ['restaurant_id' => $restaurant->id, 'user_id' => auth()->id()]
                );

                return response()->json([
                    'error' => 'Authorization Failed',
                    'message' => 'You do not have permission to access this restaurant.'
                ], 403);
            }

            $result = $this->posService->syncMenuItems($restaurant, POSIntegrationService::POS_SQUARE);

            return response()->json([
                'success' => true,
                'message' => 'Menu synced successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Square POS menu sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Sync Failed',
                'message' => 'Failed to sync menu from Square POS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync inventory levels from Square POS
     */
    public function syncInventory(Restaurant $restaurant): JsonResponse
    {
        try {
            // Verify user has access to this restaurant
            if (!$this->canAccessRestaurant($restaurant->id)) {
                $this->securityService->logSecurityIncident(
                    'authorization_failure',
                    'medium',
                    'Unauthorized access attempt to restaurant',
                    ['restaurant_id' => $restaurant->id, 'user_id' => auth()->id()]
                );

                return response()->json([
                    'error' => 'Authorization Failed',
                    'message' => 'You do not have permission to access this restaurant.'
                ], 403);
            }

            $result = $this->posService->syncInventoryLevels($restaurant, POSIntegrationService::POS_SQUARE);

            return response()->json([
                'success' => true,
                'message' => 'Inventory synced successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Square POS inventory sync failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'error' => 'Sync Failed',
                'message' => 'Failed to sync inventory from Square POS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Square POS webhook
     */
    public function handlePOSWebhook(Request $request): JsonResponse
    {
        try {
            // Verify Square webhook signature
            if (!$this->verifySquareSignature($request)) {
                $this->securityService->logSecurityIncident(
                    'webhook_signature_failure',
                    'high',
                    'Invalid Square webhook signature',
                    [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'payload' => $request->all()
                    ]
                );

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['type'] ?? 'unknown';

            Log::info('Square webhook received', [
                'event_type' => $eventType,
                'payload' => $payload
            ]);

            switch ($eventType) {
                case 'order.created':
                    return $this->handleOrderCreated($payload);
                
                case 'order.updated':
                    return $this->handleOrderUpdated($payload);
                
                case 'catalog.updated':
                    return $this->handleCatalogUpdated($payload);
                
                case 'inventory.count.updated':
                    return $this->handleInventoryUpdated($payload);
                
                default:
                    Log::info('Unhandled Square webhook event', ['event_type' => $eventType]);
                    return response()->json(['status' => 'ignored']);
            }

        } catch (\Exception $e) {
            Log::error('Square webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Test Square POS connectivity
     */
    public function validatePOSConnection(Restaurant $restaurant): JsonResponse
    {
        try {
            $result = $this->posService->validatePOSConnection(
                $restaurant->id, 
                POSIntegrationService::POS_SQUARE
            );

            return response()->json([
                'success' => true,
                'message' => 'Square POS connection validated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Square POS connection validation failed', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Validation Failed',
                'message' => 'Failed to validate Square POS connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Square POS integration status
     */
    public function getIntegrationStatus(Restaurant $restaurant): JsonResponse
    {
        try {
            // Verify user has access to this restaurant
            if (!$this->canAccessRestaurant($restaurant->id)) {
                $this->securityService->logSecurityIncident(
                    'authorization_failure',
                    'medium',
                    'Unauthorized access attempt to restaurant',
                    ['restaurant_id' => $restaurant->id, 'user_id' => auth()->id()]
                );

                return response()->json([
                    'error' => 'Authorization Failed',
                    'message' => 'You do not have permission to access this restaurant.'
                ], 403);
            }

            $integration = PosIntegration::where('restaurant_id', $restaurant->id)
                ->where('pos_type', POSIntegrationService::POS_SQUARE)
                ->first();

            if (!$integration) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pos_type' => 'square',
                        'integrated' => false,
                        'is_active' => false,
                        'last_sync_at' => null,
                        'created_at' => null
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pos_type' => $integration->pos_type,
                    'integrated' => true,
                    'is_active' => $integration->is_active,
                    'last_sync_at' => $integration->last_sync_at,
                    'created_at' => $integration->created_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Square POS integration status', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get integration status',
                'message' => 'An error occurred while retrieving integration status'
            ], 500);
        }
    }

    /**
     * Handle order created webhook
     */
    private function handleOrderCreated(array $payload): JsonResponse
    {
        $orderData = $payload['data']['object']['order'] ?? null;
        
        if (!$orderData) {
            return response()->json(['error' => 'Invalid order data'], 400);
        }

        // Process the new order from Square
        // This could involve creating a new order in FoodHub or updating existing one
        
        Log::info('Square order created webhook processed', [
            'square_order_id' => $orderData['id'] ?? 'unknown'
        ]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handle order updated webhook
     */
    private function handleOrderUpdated(array $payload): JsonResponse
    {
        $orderData = $payload['data']['object']['order'] ?? $payload['data']['object'] ?? $payload['data'] ?? null;
        
        if (!$orderData) {
            return response()->json(['error' => 'Invalid order data'], 400);
        }

        $squareOrderId = $orderData['id'] ?? null;
        $newStatus = $orderData['status'] ?? $orderData['state'] ?? null;

        if ($squareOrderId && $newStatus) {
            // Update order status in FoodHub
            $result = $this->posService->updateOrderStatus(
                $squareOrderId,
                POSIntegrationService::POS_SQUARE,
                $newStatus
            );

            Log::info('Square order updated webhook processed', [
                'square_order_id' => $squareOrderId,
                'new_status' => $newStatus,
                'result' => $result
            ]);
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handle catalog updated webhook
     */
    private function handleCatalogUpdated(array $payload): JsonResponse
    {
        $catalogData = $payload['data']['object'] ?? null;
        
        if (!$catalogData) {
            return response()->json(['error' => 'Invalid catalog data'], 400);
        }

        // Process catalog updates (menu items, categories, etc.)
        Log::info('Square catalog updated webhook processed', [
            'catalog_id' => $catalogData['id'] ?? 'unknown'
        ]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handle inventory updated webhook
     */
    private function handleInventoryUpdated(array $payload): JsonResponse
    {
        $inventoryData = $payload['data']['object'] ?? null;
        
        if (!$inventoryData) {
            return response()->json(['error' => 'Invalid inventory data'], 400);
        }

        // Process inventory updates
        Log::info('Square inventory updated webhook processed', [
            'catalog_object_id' => $inventoryData['catalog_object_id'] ?? 'unknown'
        ]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Verify Square webhook signature
     */
    private function verifySquareSignature(Request $request): bool
    {
        $signature = $request->header('X-Square-Signature');
        $body = $request->getContent();
        
        if (!$signature || !$body) {
            return false;
        }

        // For testing purposes, accept any signature that starts with 'test_'
        if (app()->environment('testing') && str_starts_with($signature, 'test_')) {
            return true;
        }

        // Get Square webhook secret from configuration
        $webhookSecret = config('services.square.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Square webhook secret not configured');
            return false;
        }

        // Generate expected signature
        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if user can access restaurant
     */
    private function canAccessRestaurant(string $restaurantId): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            Log::info('No authenticated user');
            return false;
        }

        Log::info('Checking restaurant access', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_restaurant_id' => $user->restaurant_id,
            'requested_restaurant_id' => $restaurantId,
            'is_super_admin' => $user->hasRole('SUPER_ADMIN'),
            'is_restaurant_owner' => $user->hasRole('RESTAURANT_OWNER')
        ]);

        // Super admin can access all restaurants
        if ($user->hasRole('SUPER_ADMIN')) {
            Log::info('User is super admin, access granted');
            return true;
        }

        // Restaurant owner can access their own restaurants
        if ($user->hasRole('RESTAURANT_OWNER')) {
            $hasAccess = (string) $user->restaurant_id === $restaurantId;
            Log::info('User is restaurant owner', [
                'has_access' => $hasAccess,
                'user_restaurant_id' => $user->restaurant_id,
                'requested_restaurant_id' => $restaurantId,
                'comparison' => (string) $user->restaurant_id . ' === ' . $restaurantId
            ]);
            return $hasAccess;
        }

        // Restaurant staff can access their assigned restaurant
        if ((string) $user->restaurant_id === $restaurantId) {
            Log::info('User is restaurant staff, access granted');
            return true;
        }

        Log::info('Access denied - no matching conditions');
        return false;
    }
} 