<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Http\Resources\Api\RestaurantResource;
use App\Http\Requests\StoreRestaurantRequest;
use App\Http\Requests\UpdateRestaurantRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Public endpoint - no authorization required
        
        // Validate pagination parameters
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);
        
        $restaurants = Restaurant::paginate($perPage);
        
        return response()->json([
            'success' => true,
            'message' => 'Restaurants retrieved successfully',
            'data' => RestaurantResource::collection($restaurants),
            'meta' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total(),
                'from' => $restaurants->firstItem(),
                'to' => $restaurants->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantRequest $request): Response
    {
        $this->authorize('create', Restaurant::class);

        // The request is automatically validated by StoreRestaurantRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new Restaurant record with the validated data.
        $restaurant = Restaurant::create($validated);

        // Return the newly created restaurant transformed by RestaurantResource
        // with a 201 Created status code.
        return response(new RestaurantResource($restaurant), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Restaurant $restaurant): JsonResponse
    {
        // Public endpoint - no authorization required
        // Load the branches relationship
        $restaurant->load('branches');
        
        // Return the specified restaurant transformed by RestaurantResource.
        // Laravel's route model binding automatically retrieves the restaurant.
        return response()->json([
            'success' => true,
            'message' => 'Restaurant details retrieved successfully',
            'data' => new RestaurantResource($restaurant)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): Response
    {
        $this->authorize('update', $restaurant);

        // The request is automatically validated by UpdateRestaurantRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing Restaurant record with the validated data.
        $restaurant->update($validated);

        // Return the updated restaurant transformed by RestaurantResource.
        return response(new RestaurantResource($restaurant));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Restaurant $restaurant): Response
    {
        $this->authorize('delete', $restaurant);

        // Delete the specified restaurant record.
        $restaurant->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
