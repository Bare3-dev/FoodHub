<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\DeliveryTracking;
use App\Models\WebhookLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DeliveryService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly SecurityLoggingService $securityLoggingService
    ) {}

    /**
     * Create a new driver with complete profile setup
     */
    public function createDriver(array $driverData): Driver
    {
        DB::beginTransaction();
        
        try {
            // Create driver with basic info
            $driver = Driver::create([
                'first_name' => $driverData['first_name'],
                'last_name' => $driverData['last_name'],
                'email' => $driverData['email'],
                'phone' => $driverData['phone'],
                'password' => bcrypt($driverData['password']),
                'date_of_birth' => $driverData['date_of_birth'],
                'national_id' => $driverData['national_id'],
                'driver_license_number' => $driverData['driver_license_number'],
                'license_expiry_date' => $driverData['license_expiry_date'],
                'vehicle_type' => $driverData['vehicle_type'],
                'vehicle_make' => $driverData['vehicle_make'],
                'vehicle_model' => $driverData['vehicle_model'],
                'vehicle_year' => $driverData['vehicle_year'],
                'vehicle_color' => $driverData['vehicle_color'],
                'vehicle_plate_number' => $driverData['vehicle_plate_number'],
                'status' => 'offline',
                'is_online' => false,
                'is_available' => false,
                'rating' => 5.0,
                'total_deliveries' => 0,
                'completed_deliveries' => 0,
                'cancelled_deliveries' => 0,
                'total_earnings' => 0.00,
                'documents' => [
                    'license_verified' => false,
                    'insurance_verified' => false,
                    'vehicle_registration_verified' => false,
                ],
                'banking_info' => [
                    'account_number' => $driverData['bank_account_number'] ?? null,
                    'bank_name' => $driverData['bank_name'] ?? null,
                ],
            ]);

            // Create working zones if provided
            if (isset($driverData['working_zones'])) {
                foreach ($driverData['working_zones'] as $zoneData) {
                    $driver->workingZones()->create($zoneData);
                }
            }

            // Send welcome notification
            $this->notificationService->sendDriverWelcomeNotification($driver);

            DB::commit();
            
            $this->securityLoggingService->logEvent('driver_created', $driver->id);
            
            return $driver;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create driver', [
                'error' => $e->getMessage(),
                'data' => $driverData
            ]);
            throw $e;
        }
    }

    /**
     * Update driver's current status and location
     */
    public function updateDriverStatus(Driver $driver, array $statusData): Driver
    {
        $oldStatus = $driver->status;
        $oldLocation = [
            'latitude' => $driver->current_latitude,
            'longitude' => $driver->current_longitude
        ];

        // Update status
        if (isset($statusData['status'])) {
            $driver->status = $statusData['status'];
        }

        if (isset($statusData['is_online'])) {
            $driver->is_online = $statusData['is_online'];
        }

        if (isset($statusData['is_available'])) {
            $driver->is_available = $statusData['is_available'];
        }

        // Update location if provided
        if (isset($statusData['current_latitude']) || isset($statusData['current_longitude'])) {
            $driver->current_latitude = $statusData['current_latitude'] ?? $driver->current_latitude;
            $driver->current_longitude = $statusData['current_longitude'] ?? $driver->current_longitude;
            $driver->last_location_update = now();
        }

        // Update capacity if provided
        if (isset($statusData['max_orders'])) {
            $driver->max_orders = $statusData['max_orders'];
        }

        $driver->last_active_at = now();
        $driver->save();

        // Log status change
        $this->securityLoggingService->logEvent('driver_status_updated', $driver->id, null, null, null, [
            'old_status' => $oldStatus,
            'new_status' => $driver->status,
            'old_location' => $oldLocation,
            'new_location' => [
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude
            ]
        ]);

        // Notify dispatch system
        $this->notifyDispatchSystem($driver);

        return $driver;
    }

    /**
     * Get available drivers for order assignment
     */
    public function getAvailableDrivers(float $pickupLat, float $pickupLng, array $filters = []): Collection
    {
        $query = Driver::query()
            ->where('status', 'active')
            ->where('is_online', true)
            ->where('is_available', true);

        // Filter by working zones if specified
        if (isset($filters['zone_id'])) {
            $query->whereHas('workingZones', function ($q) use ($filters) {
                $q->where('id', $filters['zone_id']);
            });
        }

        // Filter by vehicle type if specified
        if (isset($filters['vehicle_type'])) {
            $query->where('vehicle_type', $filters['vehicle_type']);
        }

        // Filter by capacity
        $query->whereRaw('(SELECT COUNT(*) FROM order_assignments WHERE driver_id = drivers.id AND status IN ("assigned", "pickup", "en_route")) < COALESCE(drivers.max_orders, 3)');

        $drivers = $query->get();

        // Calculate proximity and rank drivers
        $rankedDrivers = $drivers->map(function ($driver) use ($pickupLat, $pickupLng) {
            $distance = $this->calculateDistance(
                $pickupLat, $pickupLng,
                $driver->current_latitude, $driver->current_longitude
            );

            $score = $this->calculateDriverScore($driver, $distance);

            return [
                'driver' => $driver,
                'distance' => $distance,
                'score' => $score
            ];
        })->sortByDesc('score');

        return $rankedDrivers->pluck('driver');
    }

    /**
     * Optimize delivery route for multiple orders
     */
    public function optimizeDeliveryRoute(array $waypoints, array $constraints = []): array
    {
        // Simple implementation - in production, use Google Maps API or specialized routing service
        $optimizedRoute = $this->applyTravelingSalesmanAlgorithm($waypoints);
        
        // Apply traffic conditions if available
        if (isset($constraints['traffic_conditions'])) {
            $optimizedRoute = $this->applyTrafficConditions($optimizedRoute, $constraints['traffic_conditions']);
        }

        // Calculate fuel costs and time estimates
        $routeMetrics = $this->calculateRouteMetrics($optimizedRoute);

        return [
            'waypoints' => $optimizedRoute,
            'total_distance' => $routeMetrics['distance'],
            'total_time' => $routeMetrics['time'],
            'fuel_cost' => $routeMetrics['fuel_cost'],
            'estimated_cost' => $routeMetrics['estimated_cost']
        ];
    }

    /**
     * Calculate ETA for delivery route
     */
    public function calculateRouteETA(array $route, Driver $driver): array
    {
        $currentTime = now();
        $totalTime = 0;
        $waypoints = [];

        foreach ($route['waypoints'] as $index => $waypoint) {
            $distance = $waypoint['distance'] ?? 0;
            $trafficMultiplier = $waypoint['traffic_multiplier'] ?? 1.0;
            
            // Base time calculation (assuming 30 km/h average speed)
            $baseTime = ($distance / 30) * 60; // Convert to minutes
            $adjustedTime = $baseTime * $trafficMultiplier;
            
            // Add buffer time for stops
            $stopTime = $waypoint['stop_time'] ?? 5; // 5 minutes default
            $totalTime += $adjustedTime + $stopTime;

            $waypoints[] = [
                'location' => $waypoint['location'],
                'type' => $waypoint['type'], // pickup or delivery
                'estimated_time' => $currentTime->copy()->addMinutes($totalTime),
                'distance' => $distance,
                'traffic_conditions' => $waypoint['traffic_conditions'] ?? 'normal'
            ];
        }

        return [
            'waypoints' => $waypoints,
            'total_estimated_time' => $totalTime,
            'estimated_completion' => $currentTime->copy()->addMinutes($totalTime),
            'driver_id' => $driver->id
        ];
    }

    /**
     * Update route progress in real-time
     */
    public function updateRouteProgress(OrderAssignment $assignment, array $currentLocation): array
    {
        $driver = $assignment->driver;
        $order = $assignment->order;
        
        // Update driver location
        $driver->update([
            'current_latitude' => $currentLocation['latitude'],
            'current_longitude' => $currentLocation['longitude'],
            'last_location_update' => now()
        ]);

        // Calculate progress
        $progress = $this->calculateDeliveryProgress($assignment, $currentLocation);
        
        // Update assignment status if needed
        if ($progress['status_changed']) {
            $assignment->update(['status' => $progress['new_status']]);
        }

        // Recalculate ETA
        $newETA = $this->recalculateETA($assignment, $progress);

        // Log progress
        $this->logDeliveryProgress($assignment, $progress, $newETA);

        return [
            'progress' => $progress,
            'eta' => $newETA,
            'location' => $currentLocation
        ];
    }

    /**
     * Broadcast driver location to customers and dispatch
     */
    public function broadcastDriverLocation(Driver $driver, array $location): void
    {
        // Validate location accuracy
        if (!$this->validateLocationAccuracy($location)) {
            Log::warning('Invalid location accuracy', [
                'driver_id' => $driver->id,
                'location' => $location
            ]);
            return;
        }

        // Update driver location
        $driver->update([
            'current_latitude' => $location['latitude'],
            'current_longitude' => $location['longitude'],
            'last_location_update' => now()
        ]);

        // Get active deliveries for this driver
        $activeDeliveries = $driver->orderAssignments()
            ->whereIn('status', ['assigned', 'pickup', 'en_route'])
            ->with(['order.customer'])
            ->get();

        foreach ($activeDeliveries as $assignment) {
            $customer = $assignment->order->customer;
            $distanceToCustomer = $this->calculateDistance(
                $location['latitude'], $location['longitude'],
                $assignment->order->delivery_latitude, $assignment->order->delivery_longitude
            );

            // Notify customer if driver is approaching (within 5 minutes)
            if ($distanceToCustomer <= 2.5) { // Assuming 30 km/h average speed
                $this->notificationService->sendDriverApproachingNotification($customer, $driver, $assignment);
            }

            // Broadcast location via WebSocket/SSE (placeholder)
            $this->broadcastLocationUpdate($assignment, $location);
        }

        // Store location history
        $this->storeLocationHistory($driver, $location);
    }

    /**
     * Track delivery progress throughout lifecycle
     */
    public function trackDeliveryProgress(OrderAssignment $assignment): array
    {
        $driver = $assignment->driver;
        $order = $assignment->order;
        
        $progress = [
            'assignment_id' => $assignment->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'current_status' => $assignment->status,
            'driver_location' => [
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'last_update' => $driver->last_location_update
            ],
            'order_details' => [
                'pickup_address' => $order->pickup_address,
                'delivery_address' => $order->delivery_address,
                'estimated_pickup_time' => $order->estimated_pickup_time,
                'estimated_delivery_time' => $order->estimated_delivery_time
            ],
            'progress_percentage' => $this->calculateProgressPercentage($assignment),
            'time_remaining' => $this->calculateTimeRemaining($assignment),
            'distance_remaining' => $this->calculateDistanceRemaining($assignment)
        ];

        // Check for status transitions
        $newStatus = $this->checkStatusTransition($assignment);
        if ($newStatus && $newStatus !== $assignment->status) {
            $assignment->update(['status' => $newStatus]);
            $progress['status_changed'] = true;
            $progress['new_status'] = $newStatus;
            
            // Send notifications for status changes
            $this->handleStatusChangeNotification($assignment, $newStatus);
        }

        return $progress;
    }

    /**
     * Calculate customer ETA
     */
    public function calculateCustomerETA(Order $order, Driver $driver): array
    {
        $restaurant = $order->restaurant;
        $currentTime = now();

        // Restaurant preparation time
        $preparationTime = $order->estimated_preparation_time ?? 15; // minutes

        // Calculate delivery time based on distance and traffic
        $distance = $this->calculateDistance(
            $restaurant->latitude, $restaurant->longitude,
            $order->delivery_latitude, $order->delivery_longitude
        );

        $deliveryTime = $this->calculateDeliveryTime($distance, $driver->vehicle_type);

        // Check if driver has multiple deliveries
        $driverDeliveries = $driver->orderAssignments()
            ->whereIn('status', ['assigned', 'pickup', 'en_route'])
            ->count();

        $multiDeliveryDelay = ($driverDeliveries - 1) * 10; // 10 minutes per additional delivery

        $totalTime = $preparationTime + $deliveryTime + $multiDeliveryDelay;
        $estimatedDelivery = $currentTime->copy()->addMinutes($totalTime);

        return [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'preparation_time' => $preparationTime,
            'delivery_time' => $deliveryTime,
            'total_time' => $totalTime,
            'estimated_delivery_time' => $estimatedDelivery,
            'distance' => $distance,
            'driver_deliveries_count' => $driverDeliveries,
            'multi_delivery_delay' => $multiDeliveryDelay
        ];
    }

    /**
     * Assign order to best available driver
     */
    public function assignOrderToDriver(Order $order): ?OrderAssignment
    {
        $restaurant = $order->restaurant;
        
        // Get available drivers
        $availableDrivers = $this->getAvailableDrivers(
            $restaurant->latitude, 
            $restaurant->longitude,
            ['vehicle_type' => $this->getOptimalVehicleType($order)]
        );

        if ($availableDrivers->isEmpty()) {
            Log::warning('No available drivers for order', ['order_id' => $order->id]);
            return null;
        }

        // Select best driver based on criteria
        $bestDriver = $this->selectBestDriver($availableDrivers, $order);

        // Create assignment
        $assignment = OrderAssignment::create([
            'driver_id' => $bestDriver->id,
            'order_id' => $order->id,
            'assigned_at' => now(),
            'status' => 'assigned'
        ]);

        // Update driver capacity
        $this->updateDriverCapacity($bestDriver);

        // Send assignment notification
        $this->notificationService->sendOrderAssignmentNotification($bestDriver, $order);

        // Start order tracking
        $this->startOrderTracking($assignment);

        $this->securityLoggingService->logEvent('order_assigned', $bestDriver->id, null, null, null, [
            'order_id' => $order->id,
            'assignment_id' => $assignment->id
        ]);

        return $assignment;
    }

    /**
     * Handle driver response to order assignment
     */
    public function handleDriverResponse(OrderAssignment $assignment, string $response, ?string $reason = null): array
    {
        $driver = $assignment->driver;
        $order = $assignment->order;

        $assignment->update([
            'driver_response' => $response,
            'response_time' => now(),
            'rejection_reason' => $reason
        ]);

        if ($response === 'accepted') {
            // Lock assignment and start preparation tracking
            $assignment->update(['status' => 'accepted']);
            
            // Notify restaurant and customer
            $this->notificationService->sendAssignmentAcceptedNotification($order, $driver);
            
            $result = ['status' => 'accepted', 'message' => 'Order assignment accepted'];
            
        } else {
            // Reassign to next best driver
            $assignment->update(['status' => 'rejected']);
            
            $newAssignment = $this->reassignOrder($order, $driver);
            
            if ($newAssignment) {
                $result = [
                    'status' => 'reassigned', 
                    'message' => 'Order reassigned to another driver',
                    'new_assignment_id' => $newAssignment->id
                ];
            } else {
                $result = [
                    'status' => 'failed', 
                    'message' => 'No other drivers available'
                ];
            }
        }

        // Track rejection for optimization
        if ($response === 'rejected') {
            $this->trackRejectionReason($driver, $reason);
        }

        return $result;
    }

    /**
     * Batch multiple orders for efficient delivery
     */
    public function batchOrdersForDelivery(Collection $orders): array
    {
        $batches = [];
        $processedOrders = collect();

        foreach ($orders as $order) {
            if ($processedOrders->contains($order->id)) {
                continue;
            }

            $batch = $this->createBatchFromOrder($order, $orders);
            $batches[] = $batch;
            
            // Mark orders as processed
            $batch['orders']->each(function ($batchOrder) use ($processedOrders) {
                $processedOrders->push($batchOrder->id);
            });
        }

        return $batches;
    }

    /**
     * Send delivery notifications to customers
     */
    public function sendDeliveryNotifications(OrderAssignment $assignment, string $event): void
    {
        $order = $assignment->order;
        $driver = $assignment->driver;
        $customer = $order->customer;

        switch ($event) {
            case 'assigned':
                $this->notificationService->sendOrderAssignedNotification($customer, $driver, $order);
                break;
                
            case 'pickup':
                $this->notificationService->sendOrderPickedUpNotification($customer, $driver, $order);
                break;
                
            case 'en_route':
                $this->notificationService->sendOrderEnRouteNotification($customer, $driver, $order);
                break;
                
            case 'approaching':
                $this->notificationService->sendDriverApproachingNotification($customer, $driver, $assignment);
                break;
                
            case 'delivered':
                $this->notificationService->sendOrderDeliveredNotification($customer, $driver, $order);
                break;
                
            case 'delayed':
                $this->notificationService->sendDeliveryDelayedNotification($customer, $driver, $order);
                break;
        }
    }

    /**
     * Generate secure tracking link for customers
     */
    public function generateTrackingLink(Order $order): string
    {
        $token = $this->generateSecureToken($order);
        $expiresAt = now()->addHours(24); // 24-hour expiry

        // Store tracking token
        $order->update([
            'tracking_token' => $token,
            'tracking_expires_at' => $expiresAt
        ]);

        return route('tracking.show', ['token' => $token]);
    }

    /**
     * Handle delivery exceptions and problems
     */
    public function handleDeliveryExceptions(OrderAssignment $assignment, string $exceptionType, array $details = []): array
    {
        $order = $assignment->order;
        $driver = $assignment->driver;
        $customer = $order->customer;

        $exception = [
            'assignment_id' => $assignment->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'exception_type' => $exceptionType,
            'details' => $details,
            'timestamp' => now(),
            'status' => 'pending'
        ];

        switch ($exceptionType) {
            case 'customer_unavailable':
                $result = $this->handleCustomerUnavailable($assignment, $details);
                break;
                
            case 'address_not_found':
                $result = $this->handleAddressNotFound($assignment, $details);
                break;
                
            case 'order_quality_issue':
                $result = $this->handleOrderQualityIssue($assignment, $details);
                break;
                
            case 'delivery_delay':
                $result = $this->handleDeliveryDelay($assignment, $details);
                break;
                
            default:
                $result = ['status' => 'unknown_exception', 'message' => 'Unknown exception type'];
        }

        // Log exception
        $this->securityLoggingService->logEvent('delivery_exception', $driver->id, null, null, null, $exception);

        return $result;
    }

    /**
     * Generate delivery performance reports
     */
    public function generateDeliveryReports(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        $query = OrderAssignment::with(['driver', 'order'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (isset($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $assignments = $query->get();

        return [
            'total_deliveries' => $assignments->count(),
            'completed_deliveries' => $assignments->where('status', 'delivered')->count(),
            'cancelled_deliveries' => $assignments->where('status', 'cancelled')->count(),
            'average_delivery_time' => $this->calculateAverageDeliveryTime($assignments),
            'delivery_success_rate' => $this->calculateDeliverySuccessRate($assignments),
            'driver_performance' => $this->calculateDriverPerformance($assignments),
            'zone_analysis' => $this->analyzeDeliveryZones($assignments),
            'cost_analysis' => $this->calculateDeliveryCosts($assignments)
        ];
    }

    /**
     * Track delivery KPIs
     */
    public function trackDeliveryKPIs(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        return [
            'on_time_delivery_rate' => $this->calculateOnTimeDeliveryRate($today),
            'average_delivery_time' => $this->calculateAverageDeliveryTimeByZone(),
            'customer_satisfaction_score' => $this->calculateCustomerSatisfactionScore(),
            'driver_utilization_rate' => $this->calculateDriverUtilizationRate(),
            'fuel_efficiency' => $this->calculateFuelEfficiency(),
            'order_accuracy_rate' => $this->calculateOrderAccuracyRate()
        ];
    }

    /**
     * Optimize delivery zones based on performance data
     */
    public function optimizeDeliveryZones(): array
    {
        $historicalData = $this->getHistoricalDeliveryData();
        
        return [
            'high_demand_areas' => $this->identifyHighDemandAreas($historicalData),
            'zone_adjustments' => $this->calculateZoneAdjustments($historicalData),
            'optimal_delivery_fees' => $this->calculateOptimalDeliveryFees($historicalData),
            'workload_balance' => $this->balanceDriverWorkload($historicalData),
            'traffic_patterns' => $this->analyzeTrafficPatterns($historicalData),
            'restaurant_coverage' => $this->optimizeRestaurantCoverage($historicalData)
        ];
    }

    // Helper methods...

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calculateDriverScore(Driver $driver, float $distance): float
    {
        $ratingScore = $driver->rating * 0.3;
        $distanceScore = (1 / (1 + $distance)) * 0.4;
        $performanceScore = ($driver->completed_deliveries / max($driver->total_deliveries, 1)) * 0.3;

        return $ratingScore + $distanceScore + $performanceScore;
    }

    private function applyTravelingSalesmanAlgorithm(array $waypoints): array
    {
        // Simple nearest neighbor algorithm
        $route = [];
        $unvisited = $waypoints;
        $current = array_shift($unvisited);

        $route[] = $current;

        while (!empty($unvisited)) {
            $nearest = $this->findNearestWaypoint($current, $unvisited);
            $route[] = $nearest;
            $current = $nearest;
            $unvisited = array_filter($unvisited, fn($w) => $w !== $nearest);
        }

        return $route;
    }

    private function findNearestWaypoint(array $current, array $waypoints): array
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($waypoints as $waypoint) {
            $distance = $this->calculateDistance(
                $current['latitude'], $current['longitude'],
                $waypoint['latitude'], $waypoint['longitude']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearest = $waypoint;
            }
        }

        return $nearest;
    }

    private function validateLocationAccuracy(array $location): bool
    {
        return isset($location['latitude']) && 
               isset($location['longitude']) &&
               $location['latitude'] >= -90 && $location['latitude'] <= 90 &&
               $location['longitude'] >= -180 && $location['longitude'] <= 180;
    }

    private function notifyDispatchSystem(Driver $driver): void
    {
        // Placeholder for dispatch system notification
        Log::info('Driver status updated, notifying dispatch system', [
            'driver_id' => $driver->id,
            'status' => $driver->status,
            'is_online' => $driver->is_online,
            'is_available' => $driver->is_available
        ]);
    }

    private function broadcastLocationUpdate(OrderAssignment $assignment, array $location): void
    {
        // Placeholder for WebSocket/SSE broadcasting
        Log::info('Broadcasting location update', [
            'assignment_id' => $assignment->id,
            'location' => $location
        ]);
    }

    private function storeLocationHistory(Driver $driver, array $location): void
    {
        // Store location history for analysis
        DeliveryTracking::create([
            'driver_id' => $driver->id,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timestamp' => now(),
            'accuracy' => $location['accuracy'] ?? null
        ]);
    }

    private function generateSecureToken(Order $order): string
    {
        return hash('sha256', $order->id . $order->created_at . config('app.key'));
    }

    // Additional helper methods would be implemented here...
} 