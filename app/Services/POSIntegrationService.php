<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\PosIntegration;
use App\Models\PosSyncLog;
use App\Models\PosOrderMapping;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\UnsupportedGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * POS Integration Service
 * 
 * Handles integration with various POS systems (Square, Toast, Local)
 * including order synchronization, menu management, and inventory tracking.
 */
class POSIntegrationService
{
    /**
     * POS system types
     */
    public const POS_SQUARE = 'square';
    public const POS_TOAST = 'toast';
    public const POS_LOCAL = 'local';

    /**
     * Sync types
     */
    public const SYNC_ORDER = 'order';
    public const SYNC_MENU = 'menu';
    public const SYNC_INVENTORY = 'inventory';

    /**
     * Sync statuses
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';

    /**
     * Create a POS order from FoodHub order
     */
    public function createPOSOrder(Order $order, string $posType): array
    {
        $this->validatePOSType($posType);
        
        $integration = $this->getActiveIntegration($order->restaurant_id, $posType);
        if (!$integration) {
            throw BusinessLogicException::posNotIntegrated($posType);
        }

        try {
            $posOrderData = $this->formatOrderForPOS($order, $posType);
            
            $response = $this->sendOrderToPOS($posOrderData, $integration);
            
            if ($response['success']) {
                // Create mapping record
                PosOrderMapping::create([
                    'foodhub_order_id' => $order->id,
                    'pos_order_id' => $response['pos_order_id'],
                    'pos_type' => $posType,
                    'sync_status' => 'synced'
                ]);

                // Log successful sync
                $this->logSync($integration->id, self::SYNC_ORDER, self::STATUS_SUCCESS, [
                    'order_id' => $order->id,
                    'pos_order_id' => $response['pos_order_id']
                ]);

                return [
                    'success' => true,
                    'pos_order_id' => $response['pos_order_id'],
                    'message' => 'Order successfully synced to POS'
                ];
            }

            throw BusinessLogicException::posOrderCreationFailed($response['error'] ?? 'Unknown error');

        } catch (\Exception $e) {
            $this->logSync($integration->id, self::SYNC_ORDER, self::STATUS_FAILED, [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Update order status from POS
     */
    public function updateOrderStatus(string $posOrderId, string $posType, string $newStatus): array
    {
        $this->validatePOSType($posType);
        
        $mapping = PosOrderMapping::where('pos_order_id', $posOrderId)
            ->where('pos_type', $posType)
            ->first();

        if (!$mapping) {
            throw BusinessLogicException::posOrderNotFound($posOrderId);
        }

        $order = Order::find($mapping->foodhub_order_id);
        if (!$order) {
            throw BusinessLogicException::orderNotFound($mapping->foodhub_order_id);
        }

        // Map POS status to FoodHub status
        $foodhubStatus = $this->mapPOSStatusToFoodHub($newStatus, $posType);
        
        $order->update(['status' => $foodhubStatus]);

        // Log status update
        $integration = $this->getActiveIntegration($order->restaurant_id, $posType);
        if ($integration) {
            $this->logSync($integration->id, self::SYNC_ORDER, self::STATUS_SUCCESS, [
                'order_id' => $order->id,
                'pos_order_id' => $posOrderId,
                'status_change' => $newStatus . ' -> ' . $foodhubStatus
            ]);
        }

        return [
            'success' => true,
            'order_id' => $order->id,
            'status' => $foodhubStatus,
            'message' => 'Order status updated successfully'
        ];
    }

    /**
     * Sync menu items from POS
     */
    public function syncMenuItems(Restaurant $restaurant, string $posType): array
    {
        $this->validatePOSType($posType);
        
        $integration = $this->getActiveIntegration($restaurant->id, $posType);
        if (!$integration) {
            throw BusinessLogicException::posNotIntegrated($posType);
        }

        try {
            $posMenuData = $this->fetchMenuFromPOS($integration);
            
            $syncedItems = 0;
            $updatedItems = 0;
            $errors = [];

            foreach ($posMenuData as $posItem) {
                try {
                    $result = $this->syncMenuItem($posItem, $restaurant);
                    if ($result['action'] === 'created') {
                        $syncedItems++;
                    } elseif ($result['action'] === 'updated') {
                        $updatedItems++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'item' => $posItem['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log sync results
            $this->logSync($integration->id, self::SYNC_MENU, self::STATUS_SUCCESS, [
                'synced_items' => $syncedItems,
                'updated_items' => $updatedItems,
                'errors' => $errors
            ]);

            return [
                'success' => true,
                'synced_items' => $syncedItems,
                'updated_items' => $updatedItems,
                'errors' => $errors,
                'message' => "Menu sync completed: {$syncedItems} new, {$updatedItems} updated"
            ];

        } catch (\Exception $e) {
            $this->logSync($integration->id, self::SYNC_MENU, self::STATUS_FAILED, [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Sync inventory levels from POS
     */
    public function syncInventoryLevels(Restaurant $restaurant, string $posType): array
    {
        $this->validatePOSType($posType);
        
        $integration = $this->getActiveIntegration($restaurant->id, $posType);
        if (!$integration) {
            throw BusinessLogicException::posNotIntegrated($posType);
        }

        try {
            $posInventoryData = $this->fetchInventoryFromPOS($integration);
            
            $updatedItems = 0;
            $errors = [];

            foreach ($posInventoryData as $posItem) {
                try {
                    $menuItem = MenuItem::where('pos_item_id', $posItem['id'])
                        ->where('restaurant_id', $restaurant->id)
                        ->first();

                    if ($menuItem) {
                        $menuItem->update([
                            'stock_quantity' => $posItem['quantity'],
                            'is_available' => $posItem['quantity'] > 0
                        ]);
                        $updatedItems++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'item_id' => $posItem['id'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log sync results
            $this->logSync($integration->id, self::SYNC_INVENTORY, self::STATUS_SUCCESS, [
                'updated_items' => $updatedItems,
                'errors' => $errors
            ]);

            return [
                'success' => true,
                'updated_items' => $updatedItems,
                'errors' => $errors,
                'message' => "Inventory sync completed: {$updatedItems} items updated"
            ];

        } catch (\Exception $e) {
            $this->logSync($integration->id, self::SYNC_INVENTORY, self::STATUS_FAILED, [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle POS disconnection scenarios
     */
    public function handlePOSDisconnection(string $restaurantId, string $posType): array
    {
        $this->validatePOSType($posType);
        
        $integration = $this->getActiveIntegration($restaurantId, $posType);
        if (!$integration) {
            return ['success' => false, 'message' => 'No active integration found'];
        }

        // Mark integration as inactive
        $integration->update([
            'is_active' => false,
            'last_sync_at' => now()
        ]);

        // Log disconnection
        $this->logSync($integration->id, 'connection', self::STATUS_FAILED, [
            'event' => 'disconnection',
            'timestamp' => now()->toISOString()
        ]);

        // Notify restaurant staff about disconnection
        // This could trigger email/SMS notifications

        return [
            'success' => true,
            'message' => 'POS disconnection handled successfully',
            'integration_id' => $integration->id
        ];
    }

    /**
     * Validate POS connection
     */
    public function validatePOSConnection(string $restaurantId, string $posType): array
    {
        $this->validatePOSType($posType);
        
        $integration = $this->getActiveIntegration($restaurantId, $posType);
        if (!$integration) {
            return ['connected' => false, 'message' => 'No active integration'];
        }

        try {
            $response = $this->testPOSConnection($integration);
            
            if ($response['success']) {
                $integration->update(['last_sync_at' => now()]);
                return ['connected' => true, 'message' => 'Connection successful'];
            }

            return ['connected' => false, 'message' => $response['error'] ?? 'Connection failed'];

        } catch (\Exception $e) {
            return ['connected' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get active POS integration
     */
    private function getActiveIntegration(string $restaurantId, string $posType): ?PosIntegration
    {
        return PosIntegration::where('restaurant_id', $restaurantId)
            ->where('pos_type', $posType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Validate POS type
     */
    private function validatePOSType(string $posType): void
    {
        $validTypes = [self::POS_SQUARE, self::POS_TOAST, self::POS_LOCAL];
        
        if (!in_array($posType, $validTypes)) {
            throw new UnsupportedGatewayException("Unsupported POS type: {$posType}");
        }
    }

    /**
     * Format order for POS system
     */
    private function formatOrderForPOS(Order $order, string $posType): array
    {
        $formattedOrder = [
            'external_id' => $order->id,
            'customer' => [
                'name' => $order->customer->name ?? 'Guest',
                'phone' => $order->customer->phone ?? '',
                'email' => $order->customer->email ?? ''
            ],
            'items' => [],
            'total' => $order->total_amount,
            'tax' => $order->tax_amount ?? 0,
            'delivery_fee' => $order->delivery_fee ?? 0,
            'notes' => $order->special_instructions ?? '',
            'created_at' => $order->created_at->toISOString()
        ];

        foreach ($order->items as $item) {
            $formattedOrder['items'][] = [
                'name' => $item->menu_item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'notes' => $item->special_instructions ?? ''
            ];
        }

        // Apply POS-specific formatting
        switch ($posType) {
            case self::POS_SQUARE:
                return $this->formatForSquare($formattedOrder);
            case self::POS_TOAST:
                return $this->formatForToast($formattedOrder);
            case self::POS_LOCAL:
                return $this->formatForLocal($formattedOrder);
            default:
                return $formattedOrder;
        }
    }

    /**
     * Format order for Square POS
     */
    private function formatForSquare(array $order): array
    {
        // Square-specific formatting
        return [
            'order' => [
                'reference_id' => $order['external_id'],
                'line_items' => array_map(function($item) {
                    return [
                        'name' => $item['name'],
                        'quantity' => (string) $item['quantity'],
                        'base_price_money' => [
                            'amount' => (int) ($item['unit_price'] * 100), // Square uses cents
                            'currency' => 'USD'
                        ]
                    ];
                }, $order['items']),
                'fulfillments' => [
                    [
                        'type' => 'PICKUP',
                        'state' => 'PROPOSED'
                    ]
                ]
            ]
        ];
    }

    /**
     * Format order for Toast POS
     */
    private function formatForToast(array $order): array
    {
        // Toast-specific formatting
        return [
            'orderNumber' => $order['external_id'],
            'customer' => $order['customer'],
            'items' => array_map(function($item) {
                return [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price'],
                    'notes' => $item['notes']
                ];
            }, $order['items']),
            'total' => $order['total'],
            'notes' => $order['notes']
        ];
    }

    /**
     * Format order for Local POS
     */
    private function formatForLocal(array $order): array
    {
        // Local POS formatting (generic)
        return [
            'order_id' => $order['external_id'],
            'customer_info' => $order['customer'],
            'order_items' => $order['items'],
            'order_total' => $order['total'],
            'special_instructions' => $order['notes']
        ];
    }

    /**
     * Send order to POS system
     */
    private function sendOrderToPOS(array $orderData, PosIntegration $integration): array
    {
        $config = $integration->configuration;
        
        switch ($integration->pos_type) {
            case self::POS_SQUARE:
                return $this->sendToSquare($orderData, $config);
            case self::POS_TOAST:
                return $this->sendToToast($orderData, $config);
            case self::POS_LOCAL:
                return $this->sendToLocal($orderData, $config);
            default:
                throw new UnsupportedGatewayException("Unsupported POS type: {$integration->pos_type}");
        }
    }

    /**
     * Send order to Square
     */
    private function sendToSquare(array $orderData, array $config): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['access_token'],
            'Content-Type' => 'application/json'
        ])->post($config['api_url'] . '/v2/orders', $orderData);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'pos_order_id' => $data['order']['id'] ?? null
            ];
        }

        return [
            'success' => false,
            'error' => $response->body()
        ];
    }

    /**
     * Send order to Toast
     */
    private function sendToToast(array $orderData, array $config): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['access_token'],
            'Content-Type' => 'application/json'
        ])->post($config['api_url'] . '/orders', $orderData);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'pos_order_id' => $data['id'] ?? null
            ];
        }

        return [
            'success' => false,
            'error' => $response->body()
        ];
    }

    /**
     * Send order to Local POS
     */
    private function sendToLocal(array $orderData, array $config): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json'
        ])->post($config['api_url'] . '/orders', $orderData);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'pos_order_id' => $data['order_id'] ?? null
            ];
        }

        return [
            'success' => false,
            'error' => $response->body()
        ];
    }

    /**
     * Map POS status to FoodHub status
     */
    private function mapPOSStatusToFoodHub(string $posStatus, string $posType): string
    {
        $statusMap = [
            self::POS_SQUARE => [
                'OPEN' => 'pending',
                'COMPLETED' => 'completed',
                'CANCELED' => 'cancelled'
            ],
            self::POS_TOAST => [
                'New' => 'pending',
                'In Progress' => 'preparing',
                'Ready' => 'ready_for_pickup',
                'Completed' => 'completed',
                'Void' => 'cancelled'
            ],
            self::POS_LOCAL => [
                'pending' => 'pending',
                'preparing' => 'preparing',
                'ready' => 'ready_for_pickup',
                'completed' => 'completed',
                'cancelled' => 'cancelled'
            ]
        ];

        return $statusMap[$posType][$posStatus] ?? 'pending';
    }

    /**
     * Fetch menu from POS
     */
    private function fetchMenuFromPOS(PosIntegration $integration): array
    {
        $config = $integration->configuration;
        
        switch ($integration->pos_type) {
            case self::POS_SQUARE:
                return $this->fetchFromSquare($config, 'catalog');
            case self::POS_TOAST:
                return $this->fetchFromToast($config, 'menu');
            case self::POS_LOCAL:
                return $this->fetchFromLocal($config, 'menu');
            default:
                return [];
        }
    }

    /**
     * Fetch inventory from POS
     */
    private function fetchInventoryFromPOS(PosIntegration $integration): array
    {
        $config = $integration->configuration;
        
        switch ($integration->pos_type) {
            case self::POS_SQUARE:
                return $this->fetchFromSquare($config, 'inventory');
            case self::POS_TOAST:
                return $this->fetchFromToast($config, 'inventory');
            case self::POS_LOCAL:
                return $this->fetchFromLocal($config, 'inventory');
            default:
                return [];
        }
    }

    /**
     * Fetch data from Square
     */
    private function fetchFromSquare(array $config, string $type): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['access_token']
        ])->get($config['api_url'] . "/v2/catalog");

        if ($response->successful()) {
            return $response->json()['objects'] ?? [];
        }

        return [];
    }

    /**
     * Fetch data from Toast
     */
    private function fetchFromToast(array $config, string $type): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['access_token']
        ])->get($config['api_url'] . "/{$type}");

        if ($response->successful()) {
            return $response->json()['data'] ?? [];
        }

        return [];
    }

    /**
     * Fetch data from Local POS
     */
    private function fetchFromLocal(array $config, string $type): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key']
        ])->get($config['api_url'] . "/{$type}");

        if ($response->successful()) {
            return $response->json()['data'] ?? [];
        }

        return [];
    }

    /**
     * Sync individual menu item
     */
    private function syncMenuItem(array $posItem, Restaurant $restaurant): array
    {
        $existingItem = MenuItem::where('pos_item_id', $posItem['id'])
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if ($existingItem) {
            // Update existing item
            $existingItem->update([
                'name' => $posItem['name'],
                'price' => $posItem['price'] ?? $existingItem->price,
                'description' => $posItem['description'] ?? $existingItem->description,
                'is_available' => $posItem['is_available'] ?? true
            ]);

            return ['action' => 'updated', 'item_id' => $existingItem->id];
        } else {
            // Create new item
            $newItem = MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'pos_item_id' => $posItem['id'],
                'name' => $posItem['name'],
                'slug' => \Illuminate\Support\Str::slug($posItem['name']),
                'price' => $posItem['price'] ?? 0,
                'description' => $posItem['description'] ?? '',
                'is_available' => $posItem['is_available'] ?? true,
                'menu_category_id' => $this->getOrCreateCategory($posItem['category'] ?? 'Uncategorized', $restaurant->id)
            ]);

            return ['action' => 'created', 'item_id' => $newItem->id];
        }
    }

    /**
     * Get or create menu category
     */
    private function getOrCreateCategory(string $categoryName, string $restaurantId): string
    {
        $category = \App\Models\MenuCategory::firstOrCreate([
            'name' => $categoryName,
            'restaurant_id' => $restaurantId
        ], [
            'name' => $categoryName,
            'restaurant_id' => $restaurantId,
            'is_active' => true
        ]);

        return $category->id;
    }

    /**
     * Test POS connection
     */
    private function testPOSConnection(PosIntegration $integration): array
    {
        $config = $integration->configuration;
        
        try {
            switch ($integration->pos_type) {
                case self::POS_SQUARE:
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $config['access_token']
                    ])->get($config['api_url'] . '/v2/merchants/' . $config['merchant_id']);
                    break;
                    
                case self::POS_TOAST:
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $config['access_token']
                    ])->get($config['api_url'] . '/restaurants');
                    break;
                    
                case self::POS_LOCAL:
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $config['api_key']
                    ])->get($config['api_url'] . '/status');
                    break;
                    
                default:
                    return ['success' => false, 'error' => 'Unsupported POS type'];
            }

            if ($response->successful()) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Log sync operation
     */
    private function logSync(string $integrationId, string $syncType, string $status, array $details = []): void
    {
        PosSyncLog::create([
            'id' => Str::uuid(),
            'pos_integration_id' => $integrationId,
            'sync_type' => $syncType,
            'status' => $status,
            'details' => $details,
            'synced_at' => now()
        ]);
    }
}