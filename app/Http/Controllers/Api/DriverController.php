<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Http\Resources\Api\DriverResource;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Driver::class);

        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Build query with filters
        $query = Driver::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by availability if provided
        if ($request->has('available')) {
            $isAvailable = filter_var($request->input('available'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_available', $isAvailable);
        }

        // Retrieve drivers with pagination and transform them using DriverResource collection.
        $drivers = $query->paginate($perPage);
        
        // Return the response in the format expected by tests - with data key
        return response([
            'data' => DriverResource::collection($drivers->items()),
            'current_page' => $drivers->currentPage(),
            'last_page' => $drivers->lastPage(),
            'per_page' => $drivers->perPage(),
            'total' => $drivers->total(),
            'from' => $drivers->firstItem(),
            'to' => $drivers->lastItem(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverRequest $request): Response
    {
        $this->authorize('create', Driver::class);

        // The request is automatically validated by StoreDriverRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Hash the password before creating the driver record.
        $validated['password'] = Hash::make($validated['password']);

        // Create a new Driver record with the validated data.
        $driver = Driver::create($validated);

        // Return the newly created driver transformed by DriverResource
        // with a 201 Created status code.
        return response(new DriverResource($driver), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Driver $driver): Response
    {
        $this->authorize('view', $driver);

        // Return the specified driver transformed by DriverResource.
        // Laravel's route model binding automatically retrieves the driver.
        return response(new DriverResource($driver));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverRequest $request, Driver $driver): Response
    {
        $this->authorize('update', $driver);

        // The request is automatically validated by UpdateDriverRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // If a new password is provided, hash it before updating.
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Handle location updates
        if (isset($validated['current_latitude']) || isset($validated['current_longitude'])) {
            $validated['current_latitude'] = $validated['current_latitude'] ?? $driver->current_latitude;
            $validated['current_longitude'] = $validated['current_longitude'] ?? $driver->current_longitude;
        }

        // Handle online/availability status
        if (isset($validated['is_online'])) {
            $driver->is_online = $validated['is_online'];
        }
        
        if (isset($validated['is_available'])) {
            $driver->is_available = $validated['is_available'];
        }

        // Update the existing Driver record with the validated data.
        $driver->update($validated);

        // Return the updated driver transformed by DriverResource.
        return response(new DriverResource($driver));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver): Response
    {
        $this->authorize('delete', $driver);

        // Delete the specified driver record.
        $driver->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
