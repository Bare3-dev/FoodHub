<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Http\Resources\Api\MenuItemResource;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Requests\PaginationRequest;
use App\Traits\ApiSuccessResponse;
use App\Traits\ApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(PaginationRequest $request, Restaurant $restaurant = null): JsonResponse
    {
        // No authorization needed for public menu item browsing
        // Admin users can see all items, public users see items from active restaurants only

        // Get validated pagination parameters
        $paginationParams = $request->getPaginationParams();

        // Initialize a query builder for MenuItem model.
        $query = auth()->check() && auth()->user()->isSuperAdmin() 
            ? MenuItem::with(['restaurant', 'category'])  // Admin sees all
            : MenuItem::with(['restaurant', 'category'])->whereHas('restaurant', function ($q) {
                $q->where('status', 'active');
            }); // Public sees only from active restaurants

        // If a restaurant is provided (for nested resources), filter by its ID.
        if ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        }

        // Apply filters based on request parameters.
        if ($request->has('category_id')) {
            $query->where('menu_category_id', $request->input('category_id'));
        }

        // Apply search if provided (use validated search parameter)
        if ($paginationParams['search']) {
            $query->where(function ($q) use ($paginationParams) {
                $q->where('name', 'ILIKE', '%' . $paginationParams['search'] . '%')
                  ->orWhere('description', 'ILIKE', '%' . $paginationParams['search'] . '%');
            });
        }

        if ($request->has('is_available')) {
            $query->where('is_available', $request->input('is_available'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->input('is_featured'));
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->has('dietary_tags')) {
            $tags = explode(',', $request->input('dietary_tags'));
            foreach ($tags as $tag) {
                $query->whereJsonContains('dietary_tags', trim($tag));
            }
        }

        if ($request->has('allergens')) {
            $allergens = explode(',', $request->input('allergens'));
            foreach ($allergens as $allergen) {
                $query->whereJsonContains('allergens', trim($allergen));
            }
        }

        // Apply sorting if provided
        if ($paginationParams['sort_by']) {
            $query->orderBy($paginationParams['sort_by'], $paginationParams['sort_direction']);
        } else {
            $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        }

        // Retrieve menu items based on applied filters with pagination and transform them using MenuItemResource collection.
        // The `paginate` method automatically handles the SQL LIMIT and OFFSET and provides pagination metadata.
        $menuItems = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);
        
        return $this->successResponseWithCollection(
            MenuItemResource::collection($menuItems),
            'Menu items retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemRequest $request, Restaurant $restaurant = null): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        // The request is automatically validated by StoreMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Assign the restaurant_id based on whether it's a nested resource or provided in the request.
        if ($restaurant) {
            $validated['restaurant_id'] = $restaurant->id;
        } else {
            $validated['restaurant_id'] = $request->input('restaurant_id');
        }

        // Create the MenuItem with validated data
        $menuItem = MenuItem::create($validated);

        // Return a JSON response with the created resource and a 201 status code
        return $this->createdResponse(
            (new MenuItemResource($menuItem))->response()->getData(true)['data'],
            'Menu item created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Restaurant $restaurant = null, MenuItem $menuItem): JsonResponse
    {
        // For public access, check if the restaurant is active
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            if ($menuItem->restaurant->status !== 'active') {
                return $this->notFoundResponse('Menu item not found');
            }
        } else {
            $this->authorize('view', $menuItem);
        }

        // Load the category relationship for the menu item
        $menuItem->load('category');

        // Return the specified menu item transformed by MenuItemResource.
        // Laravel's route model binding automatically retrieves the menu item.
        return $this->successResponseWithResource(
            new MenuItemResource($menuItem),
            'Menu item details retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuItemRequest $request, Restaurant $restaurant = null, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('update', $menuItem);

        // The request is automatically validated by UpdateMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing MenuItem record with the validated data.
        $menuItem->update($validated);

        // Return the updated menu item transformed by MenuItemResource.
        return $this->updatedResponse(
            (new MenuItemResource($menuItem))->response()->getData(true)['data'],
            'Menu item updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Restaurant $restaurant = null, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('delete', $menuItem);

        // Delete the specified menu item record.
        $menuItem->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return $this->deletedResponse('Menu item deleted successfully');
    }
}
