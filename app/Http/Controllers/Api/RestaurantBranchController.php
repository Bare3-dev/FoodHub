<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantBranch;
use App\Http\Resources\Api\RestaurantBranchResource;
use App\Http\Requests\StoreRestaurantBranchRequest;
use App\Http\Requests\UpdateRestaurantBranchRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RestaurantBranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Public endpoint - no authorization required

        // Validate pagination and location parameters
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:50',
        ]);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Start building the query
        $query = RestaurantBranch::with(['restaurant', 'menuItems']);

        // Apply location filtering if parameters are provided
        if ($request->has(['latitude', 'longitude', 'radius'])) {
            $latitude = (float) $request->input('latitude');
            $longitude = (float) $request->input('longitude');
            $radius = (float) $request->input('radius');
            
            $query->withinRadius($latitude, $longitude, $radius);
        }

        // Retrieve restaurant branches with pagination and transform them using RestaurantBranchResource collection.
        $branches = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'message' => 'Restaurant branches retrieved successfully',
            'data' => RestaurantBranchResource::collection($branches)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantBranchRequest $request): Response
    {
        $this->authorize('create', RestaurantBranch::class);

        // The request is automatically validated by StoreRestaurantBranchRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new RestaurantBranch record with the validated data.
        $restaurantBranch = RestaurantBranch::create($validated);

        // Return the newly created restaurant branch transformed by RestaurantBranchResource
        // with a 201 Created status code.
        return response(new RestaurantBranchResource($restaurantBranch), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(RestaurantBranch $restaurantBranch): JsonResponse
    {
        // Public endpoint - no authorization required

        // Load the restaurant and menuItems relationships
        $restaurantBranch->load(['restaurant', 'menuItems']);

        // Return the specified restaurant branch transformed by RestaurantBranchResource.
        // Laravel's route model binding automatically retrieves the restaurant branch.
        return response()->json([
            'success' => true,
            'message' => 'Restaurant branch details retrieved successfully',
            'data' => new RestaurantBranchResource($restaurantBranch)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRestaurantBranchRequest $request, RestaurantBranch $restaurantBranch): Response
    {
        $this->authorize('update', $restaurantBranch);

        // The request is automatically validated by UpdateRestaurantBranchRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing RestaurantBranch record with the validated data.
        $restaurantBranch->update($validated);

        // Return the updated restaurant branch transformed by RestaurantBranchResource.
        return response(new RestaurantBranchResource($restaurantBranch));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RestaurantBranch $restaurantBranch): Response
    {
        $this->authorize('delete', $restaurantBranch);

        // Delete the specified restaurant branch record.
        $restaurantBranch->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
