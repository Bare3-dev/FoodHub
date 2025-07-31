<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Http\Resources\Api\RestaurantResource;
use App\Http\Requests\StoreRestaurantRequest;
use App\Http\Requests\UpdateRestaurantRequest;
use App\Http\Requests\PaginationRequest;
use App\Traits\ApiSuccessResponse;
use App\Traits\ApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RestaurantController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(PaginationRequest $request): JsonResponse
    {
        // No authorization needed for public restaurant browsing
        // Admin users can see all restaurants, public users see active restaurants only

        // Get validated pagination parameters
        $paginationParams = $request->getPaginationParams();

        // For public access, only show active restaurants
        $query = auth()->check() && auth()->user()->isSuperAdmin() 
            ? Restaurant::query()  // Admin sees all
            : Restaurant::active(); // Public sees only active

        // Apply search if provided
        if ($paginationParams['search']) {
            $query->where('name', 'like', '%' . $paginationParams['search'] . '%')
                  ->orWhere('description', 'like', '%' . $paginationParams['search'] . '%');
        }

        // Apply sorting if provided
        if ($paginationParams['sort_by']) {
            $query->orderBy($paginationParams['sort_by'], $paginationParams['sort_direction']);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Retrieve restaurants with pagination and transform them using RestaurantResource collection.
        // The `paginate` method automatically handles the SQL LIMIT and OFFSET and provides pagination metadata.
        $restaurants = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);
        
        $response = $this->successResponseWithCollection(
            RestaurantResource::collection($restaurants),
            'Restaurants retrieved successfully'
        );
        
        // Add cache headers for public endpoints
        \Log::info('Setting cache headers in controller', [
            'original_cache_control' => $response->headers->get('Cache-Control'),
            'original_etag' => $response->headers->get('ETag')
        ]);
        
        $response = $response->withHeaders([
            'Cache-Control' => 'public, max-age=300',
            'ETag' => '"' . md5($response->getContent()) . '"'
        ]);
        
        \Log::info('Cache headers set in controller', [
            'new_cache_control' => $response->headers->get('Cache-Control'),
            'new_etag' => $response->headers->get('ETag')
        ]);
        
        return $response;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantRequest $request): JsonResponse
    {
        $this->authorize('create', Restaurant::class);

        // The request is automatically validated by StoreRestaurantRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new Restaurant record with the validated data.
        $restaurant = Restaurant::create($validated);

        // Return the newly created restaurant transformed by RestaurantResource
        // with a 201 Created status code.
        return $this->createdResponse(
            (new RestaurantResource($restaurant))->response()->getData(true)['data'],
            'Restaurant created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Restaurant $restaurant): JsonResponse
    {
        // For public access, only allow viewing active restaurants
        if (!auth()->check()) {
            if ($restaurant->status !== 'active') {
                abort(404);
            }
        } else {
            // For authenticated users, check authorization
            $this->authorize('view', $restaurant);
        }

        // Load the branches relationship for the restaurant
        $restaurant->load('branches');

        // Return the specified restaurant transformed by RestaurantResource.
        // Laravel's route model binding automatically retrieves the restaurant.
        $response = $this->successResponseWithResource(
            new RestaurantResource($restaurant),
            'Restaurant details retrieved successfully'
        );
        
        return $response;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): JsonResponse
    {
        $this->authorize('update', $restaurant);

        // The request is automatically validated by UpdateRestaurantRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing Restaurant record with the validated data.
        $restaurant->update($validated);

        // Return the updated restaurant transformed by RestaurantResource.
        return $this->updatedResponse(
            (new RestaurantResource($restaurant))->response()->getData(true)['data'],
            'Restaurant updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Restaurant $restaurant): JsonResponse
    {
        $this->authorize('delete', $restaurant);

        // Delete the specified restaurant record.
        $restaurant->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return $this->deletedResponse('Restaurant deleted successfully');
    }
}
