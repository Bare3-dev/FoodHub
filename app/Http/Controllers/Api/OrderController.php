<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\Api\OrderResource;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Services\LoyaltyService;
use App\Events\NewOrderPlaced;
use App\Events\OrderStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve orders with pagination and transform them using OrderResource collection.
        return response(OrderResource::collection(Order::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request): Response
    {
        $this->authorize('create', Order::class);

        // The request is automatically validated by StoreOrderRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Extract loyalty points used for processing
        $loyaltyPointsUsed = $validated['loyalty_points_used'] ?? 0.00;
        unset($validated['loyalty_points_used']); // Remove from validated data to avoid mass assignment

        // Debug logging
        \Log::info('Order creation - loyalty points used:', [
            'loyalty_points_used' => $loyaltyPointsUsed,
            'validated_data' => $validated
        ]);

        // Create a new Order record with the validated data.
        $order = Order::create($validated);

        // Log security event for order creation
        app(\App\Services\SecurityLoggingService::class)->logSecurityEvent(
            auth()->user(),
            'order_created',
            [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'total_amount' => $order->total_amount,
                'loyalty_points_used' => $loyaltyPointsUsed
            ],
            'info',
            'App\Models\Order',
            $order->id
        );

        // Process loyalty points for the order (earning)
        $this->loyaltyService->processOrderLoyaltyPoints($order);

        // Process loyalty points redemption if points are being used
        if ($loyaltyPointsUsed > 0) {
            \Log::info('Processing loyalty points redemption:', [
                'order_id' => $order->id,
                'points_to_use' => $loyaltyPointsUsed
            ]);

            // Validate that the customer has enough points
            if ($this->loyaltyService->validatePointsRedemption($order, $loyaltyPointsUsed)) {
                $this->loyaltyService->processPointsRedemption($order, $loyaltyPointsUsed);
                \Log::info('Loyalty points redemption processed successfully');
            } else {
                \Log::warning('Loyalty points redemption validation failed');
                // If validation fails, return an error
                return response([
                    'success' => false,
                    'message' => 'Insufficient loyalty points for redemption.',
                    'errors' => [
                        'loyalty_points_used' => ['The customer does not have enough points for this redemption.']
                    ]
                ], 422);
            }
        }

        // Set the loyalty_points_used on the order if it was provided in the request
        if ($loyaltyPointsUsed > 0) {
            $order->update(['loyalty_points_used' => $loyaltyPointsUsed]);
        }

        // Broadcast new order event for real-time updates
        try {
            broadcast(new NewOrderPlaced($order))->toOthers();
        } catch (\Exception $e) {
            \Log::error('Failed to broadcast new order event', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }

        // Return the newly created order transformed by OrderResource
        // with a 201 Created status code.
        return response(new OrderResource($order), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        // Return the specified order transformed by OrderResource.
        // Laravel's route model binding automatically retrieves the order.
        return response(new OrderResource($order));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): Response
    {
        $this->authorize('update', $order);

        // The request is automatically validated by UpdateOrderRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Store previous status for broadcasting
        $previousStatus = $order->status;

        // Update the existing Order record with the validated data.
        $order->update($validated);

        // Broadcast order status update if status changed
        if (isset($validated['status']) && $validated['status'] !== $previousStatus) {
            try {
                broadcast(new OrderStatusUpdated($order, $previousStatus, $validated['status']))->toOthers();
            } catch (\Exception $e) {
                \Log::error('Failed to broadcast order status update', [
                    'order_id' => $order->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $validated['status'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Return the updated order transformed by OrderResource.
        return response(new OrderResource($order));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): Response
    {
        $this->authorize('delete', $order);

        // Delete the specified order record.
        $order->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
