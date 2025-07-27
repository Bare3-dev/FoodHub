<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantBranch;
use App\Http\Resources\Api\RestaurantBranchResource;
use App\Http\Requests\StoreRestaurantBranchRequest;
use App\Http\Requests\UpdateRestaurantBranchRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RestaurantBranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RestaurantBranch::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve restaurant branches with pagination and transform them using RestaurantBranchResource collection.
        return response(RestaurantBranchResource::collection(RestaurantBranch::paginate($perPage)));
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
    public function show(RestaurantBranch $restaurantBranch): Response
    {
        $this->authorize('view', $restaurantBranch);

        // Return the specified restaurant branch transformed by RestaurantBranchResource.
        // Laravel's route model binding automatically retrieves the restaurant branch.
        return response(new RestaurantBranchResource($restaurantBranch));
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
