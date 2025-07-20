<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Http\Resources\Api\RestaurantResource;
use App\Http\Requests\StoreRestaurantRequest;
use App\Http\Requests\UpdateRestaurantRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class RestaurantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        // This allows clients to control pagination size while preventing abuse.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve restaurants with pagination and transform them using RestaurantResource collection.
        // The `paginate` method automatically handles the SQL LIMIT and OFFSET and provides pagination metadata.
        return response(RestaurantResource::collection(Restaurant::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRestaurantRequest $request): Response
    {
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
    public function show(Restaurant $restaurant): Response
    {
        // Return the specified restaurant transformed by RestaurantResource.
        // Laravel's route model binding automatically retrieves the restaurant.
        return response(new RestaurantResource($restaurant));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): Response
    {
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
        // Delete the specified restaurant record.
        $restaurant->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
