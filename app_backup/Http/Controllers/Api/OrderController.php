<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\Api\OrderResource;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
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

        // Create a new Order record with the validated data.
        $order = Order::create($validated);

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

        // Update the existing Order record with the validated data.
        $order->update($validated);

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
