<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_deliveries' => $this['total_deliveries'],
            'completed_deliveries' => $this['completed_deliveries'],
            'cancelled_deliveries' => $this['cancelled_deliveries'],
            'average_delivery_time' => $this['average_delivery_time'],
            'delivery_success_rate' => $this['delivery_success_rate'],
            'driver_performance' => $this['driver_performance'],
            'zone_analysis' => $this['zone_analysis'],
            'cost_analysis' => $this['cost_analysis'],
            'generated_at' => now()->toISOString(),
        ];
    }
} 