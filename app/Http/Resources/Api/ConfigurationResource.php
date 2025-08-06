<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'config_key' => $this->config_key,
            'config_value' => $this->when($request->user()?->can('view', $this->resource), $this->config_value),
            'data_type' => $this->data_type,
            'description' => $this->description,
            'is_sensitive' => $this->is_sensitive,
            'is_encrypted' => $this->is_encrypted,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
} 