<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverWorkingZone;
use App\Http\Resources\Api\DriverWorkingZoneResource;
use App\Http\Requests\StoreDriverWorkingZoneRequest;
use App\Http\Requests\UpdateDriverWorkingZoneRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DriverWorkingZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Define the number of items per page, with a default of 15 and a maximum of 100.
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100);

        // Retrieve driver working zones with pagination and transform them using DriverWorkingZoneResource collection.
        return response(DriverWorkingZoneResource::collection(DriverWorkingZone::paginate($perPage)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverWorkingZoneRequest $request): Response
    {
        // The request is automatically validated by StoreDriverWorkingZoneRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Create a new DriverWorkingZone record with the validated data.
        $driverWorkingZone = DriverWorkingZone::create($validated);

        // Return the newly created driver working zone transformed by DriverWorkingZoneResource
        // with a 201 Created status code.
        return response(new DriverWorkingZoneResource($driverWorkingZone), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DriverWorkingZone $driverWorkingZone): Response
    {
        // Return the specified driver working zone transformed by DriverWorkingZoneResource.
        // Laravel's route model binding automatically retrieves the driver working zone.
        return response(new DriverWorkingZoneResource($driverWorkingZone));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverWorkingZoneRequest $request, DriverWorkingZone $driverWorkingZone): Response
    {
        // The request is automatically validated by UpdateDriverWorkingZoneRequest.
        // Access the validated data directly.
        $validated = $request->validated();

        // Update the existing DriverWorkingZone record with the validated data.
        $driverWorkingZone->update($validated);

        // Return the updated driver working zone transformed by DriverWorkingZoneResource.
        return response(new DriverWorkingZoneResource($driverWorkingZone));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DriverWorkingZone $driverWorkingZone): Response
    {
        // Delete the specified driver working zone record.
        $driverWorkingZone->delete();

        // Return a 204 No Content response, indicating successful deletion.
        return response(null, 204);
    }
}
