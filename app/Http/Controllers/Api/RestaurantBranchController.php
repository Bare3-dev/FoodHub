<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantBranch;
use App\Http\Resources\Api\RestaurantBranchResource;
use App\Http\Requests\StoreRestaurantBranchRequest;
use App\Http\Requests\UpdateRestaurantBranchRequest;
use App\Traits\ApiSuccessResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RestaurantBranchController extends Controller
{
    use ApiSuccessResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // For public access, only require authorization if user is authenticated
        if (auth()->check()) {
            $this->authorize('viewAny', RestaurantBranch::class);
        }

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Start with a base query
        $query = RestaurantBranch::with('restaurant');

        // Validate location parameters if any are provided
        $locationParams = $request->only(['latitude', 'longitude', 'radius']);
        if (!empty($locationParams)) {
            $validator = \Validator::make($locationParams, [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'required|numeric|min:0.1|max:1000'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided data is invalid.',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Apply location filtering if all parameters are valid
            $query->withinRadius($locationParams['latitude'], $locationParams['longitude'], $locationParams['radius']);
        }

        // Retrieve restaurant branches with pagination and transform them using RestaurantBranchResource collection.
        $branches = $query->paginate($perPage);
        return $this->successResponseWithCollection(
            RestaurantBranchResource::collection($branches),
            'Restaurant branches retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantBranchRequest $request): JsonResponse
    {
        $this->authorize('create', RestaurantBranch::class);

        // The request is automatically validated by StoreRestaurantBranchRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new RestaurantBranch record with the validated data.
        $restaurantBranch = RestaurantBranch::create($validated);

        // Return the newly created restaurant branch transformed by RestaurantBranchResource
        // with a 201 Created status code.
        return $this->createdResponse(
            (new RestaurantBranchResource($restaurantBranch))->response()->getData(true)['data'],
            'Restaurant branch created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(RestaurantBranch $restaurantBranch): JsonResponse
    {
        // For public access, only require authorization if user is authenticated
        if (auth()->check()) {
            $this->authorize('view', $restaurantBranch);
        }

        // Load the restaurant and menu items relationships for the branch
        $restaurantBranch->load(['restaurant', 'menuItems']);

        // Return the specified restaurant branch transformed by RestaurantBranchResource.
        // Laravel's route model binding automatically retrieves the restaurant branch.
        return $this->successResponseWithResource(
            new RestaurantBranchResource($restaurantBranch),
            'Restaurant branch retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRestaurantBranchRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        $this->authorize('update', $restaurantBranch);

        // The request is automatically validated by UpdateRestaurantBranchRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing RestaurantBranch record with the validated data.
        $restaurantBranch->update($validated);

        // Return the updated restaurant branch transformed by RestaurantBranchResource.
        return $this->updatedResponse(
            (new RestaurantBranchResource($restaurantBranch))->response()->getData(true)['data'],
            'Restaurant branch updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RestaurantBranch $restaurantBranch): JsonResponse
    {
        $this->authorize('delete', $restaurantBranch);

        // Delete the specified restaurant branch record.
        $restaurantBranch->delete();

        // Return a success response, indicating successful deletion.
        return $this->deletedResponse('Restaurant branch deleted successfully');
    }
}
