<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchMenuItem;
use App\Models\RestaurantBranch;
use App\Http\Resources\Api\BranchMenuItemResource;
use App\Http\Requests\StoreBranchMenuItemRequest;
use App\Http\Requests\UpdateBranchMenuItemRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BranchMenuItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RestaurantBranch $restaurantBranch = null): Response
    {
        // Initialize a query builder for BranchMenuItem model.
        $query = BranchMenuItem::query();

        // If a restaurant branch is provided (for nested resources), filter by its ID.
        if ($restaurantBranch) {
            $query->where('restaurant_branch_id', $restaurantBranch->id);
        }

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        // This allows clients to control pagination size while preventing abuse.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve branch menu items based on applied filters with pagination and transform them using BranchMenuItemResource collection.
        // The `paginate` method automatically handles the SQL LIMIT and OFFSET and provides pagination metadata.
        return response(BranchMenuItemResource::collection($query->paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchMenuItemRequest $request, RestaurantBranch $restaurantBranch = null): Response
    {
        // The request is automatically validated by StoreBranchMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Assign the restaurant_branch_id based on whether it's a nested resource or provided in the request.
        if ($restaurantBranch) {
            $validated['restaurant_branch_id'] = $restaurantBranch->id;
        } else {
            $validated['restaurant_branch_id'] = $request->input('restaurant_branch_id');
        }

        // Create a new BranchMenuItem record with the validated data.
        $branchMenuItem = BranchMenuItem::create($validated);

        // Return the newly created branch menu item transformed by BranchMenuItemResource
        // with a 201 Created status code.
        return response(new BranchMenuItemResource($branchMenuItem), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(RestaurantBranch $restaurantBranch = null, BranchMenuItem $branchMenuItem): Response
    {
        // If a restaurant branch is provided (nested resource), ensure the branch menu item belongs to it.
        if ($restaurantBranch && $branchMenuItem->restaurant_branch_id !== $restaurantBranch->id) {
            abort(404); // Not Found if the branch menu item does not belong to the specified restaurant branch.
        }
        // Return the specified branch menu item transformed by BranchMenuItemResource.
        // Laravel's route model binding automatically retrieves the branch menu item.
        return response(new BranchMenuItemResource($branchMenuItem));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchMenuItemRequest $request, RestaurantBranch $restaurantBranch = null, BranchMenuItem $branchMenuItem): Response
    {
        // If a restaurant branch is provided (nested resource), ensure the branch menu item belongs to it.
        if ($restaurantBranch && $branchMenuItem->restaurant_branch_id !== $restaurantBranch->id) {
            abort(404); // Not Found if the branch menu item does not belong to the specified restaurant branch.
        }

        // The request is automatically validated by UpdateBranchMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing BranchMenuItem record with the validated data.
        $branchMenuItem->update($validated);

        // Return the updated branch menu item transformed by BranchMenuItemResource.
        return response(new BranchMenuItemResource($branchMenuItem));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RestaurantBranch $restaurantBranch = null, BranchMenuItem $branchMenuItem): Response
    {
        // If a restaurant branch is provided (nested resource), ensure the branch menu item belongs to it.
        if ($restaurantBranch && $branchMenuItem->restaurant_branch_id !== $restaurantBranch->id) {
            abort(404); // Not Found if the branch menu item does not belong to the specified restaurant branch.
        }
        // Delete the specified branch menu item record.
        $branchMenuItem->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
