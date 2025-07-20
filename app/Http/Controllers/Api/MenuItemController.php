<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Http\Resources\Api\MenuItemResource;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Restaurant $restaurant = null): Response
    {
        // Initialize a query builder for MenuItem model.
        $query = MenuItem::query();

        // If a restaurant is provided (for nested resources), filter by its ID.
        if ($restaurant) {
            $query->where('restaurant_id', $restaurant->id);
        }

        // Apply filters based on request parameters.
        if ($request->has('category_id')) {
            $query->where('menu_category_id', $request->input('category_id'));
        }

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', '%' . $searchTerm . '%')
                  ->orWhere('description', 'ILIKE', '%' . $searchTerm . '%');
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

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        // This allows clients to control pagination size while preventing abuse.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve menu items based on applied filters with pagination and transform them using MenuItemResource collection.
        // The `paginate` method automatically handles the SQL LIMIT and OFFSET and provides pagination metadata.
        return response(MenuItemResource::collection($query->paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemRequest $request, Restaurant $restaurant = null): Response
    {
        // The request is automatically validated by StoreMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Assign the restaurant_id based on whether it's a nested resource or provided in the request.
        if ($restaurant) {
            $validated['restaurant_id'] = $restaurant->id;
        } else {
            $validated['restaurant_id'] = $request->input('restaurant_id');
        }

        // Create a new MenuItem record with the validated data.
        $menuItem = MenuItem::create($validated);

        // Return the newly created menu item transformed by MenuItemResource
        // with a 201 Created status code.
        return response(new MenuItemResource($menuItem), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Restaurant $restaurant = null, MenuItem $menuItem): Response
    {
        // If a restaurant is provided (nested resource), ensure the menu item belongs to it.
        if ($restaurant && $menuItem->restaurant_id !== $restaurant->id) {
            abort(404); // Not Found if the menu item does not belong to the specified restaurant.
        }
        // Return the specified menu item transformed by MenuItemResource.
        // Laravel's route model binding automatically retrieves the menu item.
        return response(new MenuItemResource($menuItem));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuItemRequest $request, Restaurant $restaurant = null, MenuItem $menuItem): Response
    {
        // If a restaurant is provided (nested resource), ensure the menu item belongs to it.
        if ($restaurant && $menuItem->restaurant_id !== $restaurant->id) {
            abort(404); // Not Found if the menu item does not belong to the specified restaurant.
        }

        // The request is automatically validated by UpdateMenuItemRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing MenuItem record with the validated data.
        $menuItem->update($validated);

        // Return the updated menu item transformed by MenuItemResource.
        return response(new MenuItemResource($menuItem));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Restaurant $restaurant = null, MenuItem $menuItem): Response
    {
        // If a restaurant is provided (nested resource), ensure the menu item belongs to it.
        if ($restaurant && $menuItem->restaurant_id !== $restaurant->id) {
            abort(404); // Not Found if the menu item does not belong to the specified restaurant.
        }
        // Delete the specified menu item record.
        $menuItem->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
