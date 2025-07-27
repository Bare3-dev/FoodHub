<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Http\Resources\Api\MenuCategoryResource;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Requests\UpdateMenuCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MenuCategory::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve menu categories with pagination and transform them using MenuCategoryResource collection.
        return response(MenuCategoryResource::collection(MenuCategory::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuCategoryRequest $request): Response
    {
        // Policy check for authorization before creating the resource
        $this->authorize('create', MenuCategory::class);

        // Create the MenuCategory with validated data
        $menuCategory = MenuCategory::create($request->validated());

        // Return a JSON response with the created resource and a 201 status code
        return response(new MenuCategoryResource($menuCategory), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuCategory $menuCategory): Response
    {
        $this->authorize('view', $menuCategory);

        // Return the specified menu category transformed by MenuCategoryResource.
        // Laravel's route model binding automatically retrieves the menu category.
        return response(new MenuCategoryResource($menuCategory));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): Response
    {
        $this->authorize('update', $menuCategory);

        // The request is automatically validated by UpdateMenuCategoryRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing MenuCategory record with the validated data.
        $menuCategory->update($validated);

        // Return the updated menu category transformed by MenuCategoryResource.
        return response(new MenuCategoryResource($menuCategory));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuCategory $menuCategory): Response
    {
        $this->authorize('delete', $menuCategory);

        // Delete the specified menu category record.
        $menuCategory->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
