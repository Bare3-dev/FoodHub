<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverWorkingZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'zone_name' => $this->zone_name,
            'zone_description' => $this->zone_description,
            'coordinates' => $this->coordinates,
            'latitude' => $this->coordinates['latitude'] ?? null,
            'longitude' => $this->coordinates['longitude'] ?? null,
            'radius_km' => $this->radius_km,
            'is_active' => $this->is_active,
            'priority_level' => $this->priority_level,
            'start_time' => $this->start_time?->format('H:i:s'),
            'end_time' => $this->end_time?->format('H:i:s'),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'driver' => new DriverResource($this->whenLoaded('driver')),
        ];

        // Add distance calculation if requested
        if ($request->has('calculate_distance') && $request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $zoneLat = $this->coordinates['latitude'] ?? null;
            $zoneLng = $this->coordinates['longitude'] ?? null;
            
            if ($zoneLat && $zoneLng) {
                $data['distance_km'] = $this->calculateDistance($lat, $lng, $zoneLat, $zoneLng);
            }
        }

        // Add zone boundary detection if requested
        if ($request->has('check_address') && $request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $zoneLat = $this->coordinates['latitude'] ?? null;
            $zoneLng = $this->coordinates['longitude'] ?? null;
            
            if ($zoneLat && $zoneLng) {
                $distance = $this->calculateDistance($lat, $lng, $zoneLat, $zoneLng);
                $data['address_within_zone'] = $distance <= $this->radius_km;
            }
        }

        return $data;
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
}
