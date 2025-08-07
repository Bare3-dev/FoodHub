<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\UpdateDriverStatusRequest;
use App\Http\Requests\Delivery\AssignOrderRequest;
use App\Http\Requests\Delivery\DriverResponseRequest;
use App\Http\Requests\Delivery\LocationUpdateRequest;
use App\Http\Requests\Delivery\DeliveryExceptionRequest;
use App\Http\Resources\Api\DriverResource;
use App\Http\Resources\Api\OrderAssignmentResource;
use App\Http\Resources\Api\DeliveryTrackingResource;
use App\Http\Resources\Api\DeliveryReportResource;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\DeliveryTracking;
use App\Services\DeliveryService;
use App\Traits\ApiErrorResponse;
use App\Traits\ApiSuccessResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

final class DeliveryController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    public function __construct(
        private readonly DeliveryService $deliveryService
    ) {}

    /**
     * Create a new driver
     */
    public function createDriver(Request $request): JsonResponse
    {
        try {
            $driverData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:drivers,email',
                'phone' => 'required|string|max:20',
                'password' => 'required|string|min:8',
                'date_of_birth' => 'required|date',
                'national_id' => 'required|string|max:50|unique:drivers,national_id',
                'driver_license_number' => 'required|string|max:50|unique:drivers,driver_license_number',
                'license_expiry_date' => 'required|date|after:today',
                'vehicle_type' => 'required|string|in:car,motorcycle,bicycle',
                'vehicle_make' => 'required|string|max:100',
                'vehicle_model' => 'required|string|max:100',
                'vehicle_year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'vehicle_color' => 'required|string|max:50',
                'vehicle_plate_number' => 'required|string|max:20|unique:drivers,vehicle_plate_number',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'working_zones' => 'nullable|array',
                'working_zones.*.zone_name' => 'required|string|max:255',
                'working_zones.*.latitude' => 'required|numeric',
                'working_zones.*.longitude' => 'required|numeric',
                'working_zones.*.radius' => 'required|numeric|min:0.1',
            ]);

            $driver = $this->deliveryService->createDriver($driverData);

            return $this->successResponse(
                'Driver created successfully',
                new DriverResource($driver),
                201
            );

        } catch (Exception $e) {
            Log::error('Failed to create driver', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to create driver: ' . $e->getMessage());
        }
    }

    /**
     * Update driver status and location
     */
    public function updateDriverStatus(UpdateDriverStatusRequest $request, Driver $driver): JsonResponse
    {
        try {
            $statusData = $request->validated();
            
            $updatedDriver = $this->deliveryService->updateDriverStatus($driver, $statusData);

            return $this->successResponse(
                'Driver status updated successfully',
                new DriverResource($updatedDriver)
            );

        } catch (Exception $e) {
            Log::error('Failed to update driver status', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to update driver status: ' . $e->getMessage());
        }
    }

    /**
     * Get available drivers for order assignment
     */
    public function getAvailableDrivers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pickup_latitude' => 'required|numeric|between:-90,90',
                'pickup_longitude' => 'required|numeric|between:-180,180',
                'zone_id' => 'nullable|exists:driver_working_zones,id',
                'vehicle_type' => 'nullable|string|in:car,motorcycle,bicycle',
                'max_distance' => 'nullable|numeric|min:0',
            ]);

            $filters = $request->only(['zone_id', 'vehicle_type']);
            if ($request->has('max_distance')) {
                $filters['max_distance'] = $request->max_distance;
            }

            $drivers = $this->deliveryService->getAvailableDrivers(
                $request->pickup_latitude,
                $request->pickup_longitude,
                $filters
            );

            return $this->successResponse(
                'Available drivers retrieved successfully',
                DriverResource::collection($drivers)
            );

        } catch (Exception $e) {
            Log::error('Failed to get available drivers', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to get available drivers: ' . $e->getMessage());
        }
    }

    /**
     * Optimize delivery route for multiple orders
     */
    public function optimizeDeliveryRoute(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'waypoints' => 'required|array|min:2',
                'waypoints.*.latitude' => 'required|numeric|between:-90,90',
                'waypoints.*.longitude' => 'required|numeric|between:-180,180',
                'waypoints.*.type' => 'required|string|in:pickup,delivery',
                'waypoints.*.order_id' => 'nullable|exists:orders,id',
                'constraints' => 'nullable|array',
                'constraints.traffic_conditions' => 'nullable|array',
                'constraints.priorities' => 'nullable|array',
            ]);

            $optimizedRoute = $this->deliveryService->optimizeDeliveryRoute(
                $request->waypoints,
                $request->constraints ?? []
            );

            return $this->successResponse(
                'Route optimized successfully',
                $optimizedRoute
            );

        } catch (Exception $e) {
            Log::error('Failed to optimize route', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to optimize route: ' . $e->getMessage());
        }
    }

    /**
     * Calculate ETA for delivery route
     */
    public function calculateRouteETA(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'route' => 'required|array',
                'route.waypoints' => 'required|array',
                'driver_id' => 'required|exists:drivers,id',
            ]);

            $driver = Driver::findOrFail($request->driver_id);
            $eta = $this->deliveryService->calculateRouteETA($request->route, $driver);

            return $this->successResponse(
                'ETA calculated successfully',
                $eta
            );

        } catch (Exception $e) {
            Log::error('Failed to calculate ETA', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to calculate ETA: ' . $e->getMessage());
        }
    }

    /**
     * Update route progress in real-time
     */
    public function updateRouteProgress(LocationUpdateRequest $request, OrderAssignment $assignment): JsonResponse
    {
        try {
            $locationData = $request->validated();
            
            $progress = $this->deliveryService->updateRouteProgress($assignment, $locationData);

            return $this->successResponse(
                'Route progress updated successfully',
                $progress
            );

        } catch (Exception $e) {
            Log::error('Failed to update route progress', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to update route progress: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast driver location
     */
    public function broadcastDriverLocation(LocationUpdateRequest $request, Driver $driver): JsonResponse
    {
        try {
            $locationData = $request->validated();
            
            $this->deliveryService->broadcastDriverLocation($driver, $locationData);

            return $this->successResponse(
                'Driver location broadcasted successfully'
            );

        } catch (Exception $e) {
            Log::error('Failed to broadcast driver location', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to broadcast driver location: ' . $e->getMessage());
        }
    }

    /**
     * Track delivery progress
     */
    public function trackDeliveryProgress(OrderAssignment $assignment): JsonResponse
    {
        try {
            $progress = $this->deliveryService->trackDeliveryProgress($assignment);

            return $this->successResponse(
                'Delivery progress tracked successfully',
                $progress
            );

        } catch (Exception $e) {
            Log::error('Failed to track delivery progress', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to track delivery progress: ' . $e->getMessage());
        }
    }

    /**
     * Calculate customer ETA
     */
    public function calculateCustomerETA(Order $order): JsonResponse
    {
        try {
            $assignment = $order->orderAssignment;
            
            if (!$assignment || !$assignment->driver) {
                return $this->errorResponse('No driver assigned to this order');
            }

            $eta = $this->deliveryService->calculateCustomerETA($order, $assignment->driver);

            return $this->successResponse(
                'Customer ETA calculated successfully',
                $eta
            );

        } catch (Exception $e) {
            Log::error('Failed to calculate customer ETA', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to calculate customer ETA: ' . $e->getMessage());
        }
    }

    /**
     * Assign order to driver
     */
    public function assignOrderToDriver(AssignOrderRequest $request): JsonResponse
    {
        try {
            $order = Order::findOrFail($request->order_id);
            
            $assignment = $this->deliveryService->assignOrderToDriver($order);

            if (!$assignment) {
                return $this->errorResponse('No available drivers for this order');
            }

            return $this->successResponse(
                'Order assigned to driver successfully',
                new OrderAssignmentResource($assignment)
            );

        } catch (Exception $e) {
            Log::error('Failed to assign order to driver', [
                'order_id' => $request->order_id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to assign order to driver: ' . $e->getMessage());
        }
    }

    /**
     * Handle driver response to order assignment
     */
    public function handleDriverResponse(DriverResponseRequest $request, OrderAssignment $assignment): JsonResponse
    {
        try {
            $responseData = $request->validated();
            
            $result = $this->deliveryService->handleDriverResponse(
                $assignment,
                $responseData['response'],
                $responseData['reason'] ?? null
            );

            return $this->successResponse(
                'Driver response processed successfully',
                $result
            );

        } catch (Exception $e) {
            Log::error('Failed to handle driver response', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to handle driver response: ' . $e->getMessage());
        }
    }

    /**
     * Batch orders for delivery
     */
    public function batchOrdersForDelivery(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:2',
                'order_ids.*' => 'exists:orders,id',
            ]);

            $orders = Order::whereIn('id', $request->order_ids)->get();
            
            $batches = $this->deliveryService->batchOrdersForDelivery($orders);

            return $this->successResponse(
                'Orders batched for delivery successfully',
                $batches
            );

        } catch (Exception $e) {
            Log::error('Failed to batch orders for delivery', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to batch orders for delivery: ' . $e->getMessage());
        }
    }

    /**
     * Send delivery notifications
     */
    public function sendDeliveryNotifications(Request $request, OrderAssignment $assignment): JsonResponse
    {
        try {
            $request->validate([
                'event' => 'required|string|in:assigned,pickup,en_route,approaching,delivered,delayed',
            ]);

            $this->deliveryService->sendDeliveryNotifications($assignment, $request->event);

            return $this->successResponse(
                'Delivery notification sent successfully'
            );

        } catch (Exception $e) {
            Log::error('Failed to send delivery notification', [
                'assignment_id' => $assignment->id,
                'event' => $request->event,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to send delivery notification: ' . $e->getMessage());
        }
    }

    /**
     * Generate tracking link
     */
    public function generateTrackingLink(Order $order): JsonResponse
    {
        try {
            $trackingLink = $this->deliveryService->generateTrackingLink($order);

            return $this->successResponse(
                'Tracking link generated successfully',
                ['tracking_url' => $trackingLink]
            );

        } catch (Exception $e) {
            Log::error('Failed to generate tracking link', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to generate tracking link: ' . $e->getMessage());
        }
    }

    /**
     * Handle delivery exceptions
     */
    public function handleDeliveryExceptions(DeliveryExceptionRequest $request, OrderAssignment $assignment): JsonResponse
    {
        try {
            $exceptionData = $request->validated();
            
            $result = $this->deliveryService->handleDeliveryExceptions(
                $assignment,
                $exceptionData['exception_type'],
                $exceptionData['details'] ?? []
            );

            return $this->successResponse(
                'Delivery exception handled successfully',
                $result
            );

        } catch (Exception $e) {
            Log::error('Failed to handle delivery exception', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to handle delivery exception: ' . $e->getMessage());
        }
    }

    /**
     * Generate delivery reports
     */
    public function generateDeliveryReports(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'driver_id' => 'nullable|exists:drivers,id',
                'status' => 'nullable|string|in:assigned,pickup,en_route,delivered,cancelled',
            ]);

            $filters = $request->only(['start_date', 'end_date', 'driver_id', 'status']);
            
            $reports = $this->deliveryService->generateDeliveryReports($filters);

            return $this->successResponse(
                'Delivery reports generated successfully',
                new DeliveryReportResource($reports)
            );

        } catch (Exception $e) {
            Log::error('Failed to generate delivery reports', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to generate delivery reports: ' . $e->getMessage());
        }
    }

    /**
     * Track delivery KPIs
     */
    public function trackDeliveryKPIs(): JsonResponse
    {
        try {
            $kpis = $this->deliveryService->trackDeliveryKPIs();

            return $this->successResponse(
                'Delivery KPIs tracked successfully',
                $kpis
            );

        } catch (Exception $e) {
            Log::error('Failed to track delivery KPIs', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to track delivery KPIs: ' . $e->getMessage());
        }
    }

    /**
     * Optimize delivery zones
     */
    public function optimizeDeliveryZones(): JsonResponse
    {
        try {
            $optimization = $this->deliveryService->optimizeDeliveryZones();

            return $this->successResponse(
                'Delivery zones optimized successfully',
                $optimization
            );

        } catch (Exception $e) {
            Log::error('Failed to optimize delivery zones', ['error' => $e->getMessage()]);
            return $this->errorResponse('Failed to optimize delivery zones: ' . $e->getMessage());
        }
    }

    /**
     * Get delivery tracking history
     */
    public function getDeliveryTrackingHistory(Request $request, Driver $driver): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'order_assignment_id' => 'nullable|exists:order_assignments,id',
            ]);

            $query = DeliveryTracking::where('driver_id', $driver->id);

            if ($request->has('start_date')) {
                $query->where('timestamp', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('timestamp', '<=', $request->end_date);
            }

            if ($request->has('order_assignment_id')) {
                $query->where('order_assignment_id', $request->order_assignment_id);
            }

            $trackingHistory = $query->orderBy('timestamp', 'desc')->paginate(100);

            return $this->successResponse(
                'Delivery tracking history retrieved successfully',
                DeliveryTrackingResource::collection($trackingHistory)
            );

        } catch (Exception $e) {
            Log::error('Failed to get delivery tracking history', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get delivery tracking history: ' . $e->getMessage());
        }
    }
} 