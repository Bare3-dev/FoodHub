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
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve drivers with pagination and transform them using DriverResource collection.
        return response(DriverResource::collection(Driver::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverRequest $request): Response
    {
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
        // Return the specified driver transformed by DriverResource.
        // Laravel's route model binding automatically retrieves the driver.
        return response(new DriverResource($driver));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverRequest $request, Driver $driver): Response
    {
        // The request is automatically validated by UpdateDriverRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // If a new password is provided, hash it before updating.
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
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
        // Delete the specified driver record.
        $driver->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
