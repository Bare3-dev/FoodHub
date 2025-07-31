<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverWorkingZone;
use App\Models\Driver;
use App\Http\Resources\Api\DriverWorkingZoneResource;
use App\Http\Requests\StoreDriverWorkingZoneRequest;
use App\Http\Requests\UpdateDriverWorkingZoneRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

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

        // Build query with filters
        $query = DriverWorkingZone::query();
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Add distance calculation if requested
        if ($request->has('calculate_distance') && $request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            
            // We'll calculate distance in PHP after fetching the results
            // to avoid complex SQL JSON operations
        }

        // Add zone boundary detection if requested
        if ($request->has('check_address') && $request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            
            // We'll calculate zone inclusion in PHP after fetching the results
        }

        // Retrieve driver working zones with pagination and transform them using DriverWorkingZoneResource collection.
        $zones = $query->with('driver')->paginate($perPage);
        
        // Return the response in the format expected by tests - with data key
        return response([
            'data' => DriverWorkingZoneResource::collection($zones->items()),
            'current_page' => $zones->currentPage(),
            'last_page' => $zones->lastPage(),
            'per_page' => $zones->perPage(),
            'total' => $zones->total(),
            'from' => $zones->firstItem(),
            'to' => $zones->lastItem(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverWorkingZoneRequest $request): Response
    {
        // The request is automatically validated by StoreDriverWorkingZoneRequest.
        // Access the validated data directly.
        $validated = $request->validated();
        
        // Transform latitude and longitude into coordinates array
        $validated['coordinates'] = [
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude']
        ];
        
        // Remove the separate latitude and longitude fields
        unset($validated['latitude'], $validated['longitude']);

        // Create a new DriverWorkingZone record with the validated data.
        $driverWorkingZone = DriverWorkingZone::create($validated);

        // Return the newly created driver working zone transformed by DriverWorkingZoneResource
        // with a 201 Created status code.
        return response(new DriverWorkingZoneResource($driverWorkingZone->load('driver')), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DriverWorkingZone $driverWorkingZone): Response
    {
        // Return the specified driver working zone transformed by DriverWorkingZoneResource.
        // Laravel's route model binding automatically retrieves the driver working zone.
        return response(new DriverWorkingZoneResource($driverWorkingZone->load('driver')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverWorkingZoneRequest $request, DriverWorkingZone $driverWorkingZone): Response
    {
        // The request is automatically validated by UpdateDriverWorkingZoneRequest.
        // Access the validated data directly.
        $validated = $request->validated();
        
        // Transform latitude and longitude into coordinates array if provided
        if (isset($validated['latitude']) && isset($validated['longitude'])) {
            $validated['coordinates'] = [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude']
            ];
            
            // Remove the separate latitude and longitude fields
            unset($validated['latitude'], $validated['longitude']);
        }

        // Update the existing DriverWorkingZone record with the validated data.
        $driverWorkingZone->update($validated);

        // Return the updated driver working zone transformed by DriverWorkingZoneResource.
        return response(new DriverWorkingZoneResource($driverWorkingZone->load('driver')));
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

    /**
     * Optimize route for multiple stops
     */
    public function optimizeRoute(Request $request): JsonResponse
    {
        $request->validate([
            'stops' => 'required|array|min:2',
            'stops.*.latitude' => 'required|numeric|between:-90,90',
            'stops.*.longitude' => 'required|numeric|between:-180,180',
        ]);

        // Simple route optimization logic (nearest neighbor)
        $stops = $request->input('stops');
        $optimizedRoute = $this->calculateOptimizedRoute($stops);

        return response()->json([
            'data' => [
                'optimized_route' => $optimizedRoute,
                'total_distance' => $this->calculateTotalDistance($optimizedRoute),
                'estimated_time' => $this->calculateEstimatedTime($optimizedRoute)
            ]
        ]);
    }

    /**
     * Assign driver based on proximity and availability
     */
    public function assignDriver(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $orderId = $request->input('order_id');

        // Find nearest available driver
        $nearestDriver = Driver::where('is_available', true)
            ->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(current_latitude)) * 
                    cos(radians(current_longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(current_latitude))
                )
            ) as distance_km', [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->first();

        if (!$nearestDriver) {
            return response()->json([
                'success' => false,
                'message' => 'No available drivers found'
            ], 404);
        }

        return response()->json([
            'data' => [
                'driver_id' => $nearestDriver->id,
                'driver_name' => $nearestDriver->first_name . ' ' . $nearestDriver->last_name,
                'distance_km' => round($nearestDriver->distance_km, 2),
                'estimated_pickup_time' => now()->addMinutes(round($nearestDriver->distance_km * 2))
            ]
        ]);
    }

    /**
     * Calculate estimated delivery time
     */
    public function calculateDeliveryTime(Request $request): JsonResponse
    {
        $request->validate([
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
        ]);

        $pickupLat = $request->input('pickup_latitude');
        $pickupLng = $request->input('pickup_longitude');
        $deliveryLat = $request->input('delivery_latitude');
        $deliveryLng = $request->input('delivery_longitude');

        // Calculate distance using Haversine formula
        $distance = $this->calculateDistance($pickupLat, $pickupLng, $deliveryLat, $deliveryLng);
        
        // Estimate delivery time (assuming 30 km/h average speed)
        $estimatedMinutes = round($distance * 2); // 2 minutes per km

        return response()->json([
            'data' => [
                'distance_km' => round($distance, 2),
                'estimated_minutes' => $estimatedMinutes,
                'estimated_arrival' => now()->addMinutes($estimatedMinutes)->toDateTimeString()
            ]
        ]);
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return 6371 * $c; // Earth's radius in km
    }

    /**
     * Calculate optimized route using nearest neighbor algorithm
     */
    private function calculateOptimizedRoute(array $stops): array
    {
        // Simple nearest neighbor implementation
        $route = [];
        $unvisited = $stops;
        $current = array_shift($unvisited);
        $route[] = $current;

        while (!empty($unvisited)) {
            $nearest = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($unvisited as $index => $stop) {
                $distance = $this->calculateDistance(
                    $current['latitude'], $current['longitude'],
                    $stop['latitude'], $stop['longitude']
                );

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearest = $index;
                }
            }

            $current = $unvisited[$nearest];
            $route[] = $current;
            unset($unvisited[$nearest]);
        }

        return $route;
    }

    /**
     * Calculate total distance of route
     */
    private function calculateTotalDistance(array $route): float
    {
        $totalDistance = 0;
        for ($i = 0; $i < count($route) - 1; $i++) {
            $totalDistance += $this->calculateDistance(
                $route[$i]['latitude'], $route[$i]['longitude'],
                $route[$i + 1]['latitude'], $route[$i + 1]['longitude']
            );
        }
        return $totalDistance;
    }

    /**
     * Calculate estimated time for route
     */
    private function calculateEstimatedTime(array $route): int
    {
        $totalDistance = $this->calculateTotalDistance($route);
        return round($totalDistance * 2); // 2 minutes per km
    }
}
