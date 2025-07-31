<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Http\Resources\Api\MenuCategoryResource;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use App\Http\Requests\PaginationRequest;
use App\Traits\ApiSuccessResponse;
use App\Traits\ApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MenuCategoryController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(PaginationRequest $request, $restaurant = null): JsonResponse
    {
        // No authorization needed for public menu category browsing
        // Admin users can see all categories, public users see categories from active restaurants only

        // Get validated pagination parameters
        $paginationParams = $request->getPaginationParams();

        // For public access, only show categories from active restaurants
        $query = auth()->check() && auth()->user()->isSuperAdmin() 
            ? MenuCategory::with('restaurant')  // Admin sees all
            : MenuCategory::with('restaurant')->whereHas('restaurant', function ($q) {
                $q->where('status', 'active');
            }); // Public sees only from active restaurants

        // If a restaurant is specified in the route, filter by that restaurant
        if ($restaurant) {
            // Handle both string ID and model object
            $restaurantId = is_numeric($restaurant) ? $restaurant : $restaurant->id;
            $query->where('restaurant_id', $restaurantId);
        }

        // Apply search if provided
        if ($paginationParams['search']) {
            $query->where('name', 'like', '%' . $paginationParams['search'] . '%')
                  ->orWhere('description', 'like', '%' . $paginationParams['search'] . '%');
        }

        // Apply sorting if provided
        if ($paginationParams['sort_by']) {
            $query->orderBy($paginationParams['sort_by'], $paginationParams['sort_direction']);
        } else {
            $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        }

        // Retrieve menu categories with pagination and transform them using MenuCategoryResource collection.
        $categories = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);
        
        return $this->successResponseWithCollection(
            MenuCategoryResource::collection($categories),
            'Menu categories retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        // Policy check for authorization before creating the resource
        $this->authorize('create', MenuCategory::class);

        // Create the MenuCategory with validated data
        $menuCategory = MenuCategory::create($request->validated());

        // Return a JSON response with the created resource and a 201 status code
        return $this->createdResponse(
            (new MenuCategoryResource($menuCategory))->response()->getData(true)['data'],
            'Menu category created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuCategory $menuCategory): JsonResponse
    {
        // Load the relationships needed for the response
        $menuCategory->load(['restaurant', 'menuItems']);

        // For public access, check if the restaurant is active
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            if ($menuCategory->restaurant->status !== 'active') {
                return $this->notFoundResponse('Menu category not found');
            }
        } else {
            $this->authorize('view', $menuCategory);
        }

        // Return the specified menu category transformed by MenuCategoryResource.
        // Laravel's route model binding automatically retrieves the menu category.
        return $this->successResponseWithResource(
            new MenuCategoryResource($menuCategory),
            'Menu category details retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $this->authorize('update', $menuCategory);

        // The request is automatically validated by UpdateMenuCategoryRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing MenuCategory record with the validated data.
        $menuCategory->update($validated);

        // Return the updated menu category transformed by MenuCategoryResource.
        return $this->updatedResponse(
            (new MenuCategoryResource($menuCategory))->response()->getData(true)['data'],
            'Menu category updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuCategory $menuCategory): JsonResponse
    {
        $this->authorize('delete', $menuCategory);

        // Delete the specified menu category record.
        $menuCategory->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return $this->deletedResponse('Menu category deleted successfully');
    }
}
