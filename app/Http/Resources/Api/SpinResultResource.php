<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpinResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'spin_wheel_id' => $this->spin_wheel_id,
            'spin_type' => $this->spin_type,
            'prize' => [
                'id' => $this->prize->id ?? null,
                'name' => $this->prize_name,
                'type' => $this->prize_type,
                'value' => $this->prize_value,
                'display_value' => $this->display_value,
                'description' => $this->prize_description,
            ],
            'status' => [
                'is_redeemed' => $this->is_redeemed,
                'can_be_redeemed' => $this->can_be_redeemed,
                'is_expired' => $this->is_expired,
            ],
            'redemption' => [
                'redeemed_at' => $this->redeemed_at?->toISOString(),
                'redeemed_by_order_id' => $this->redeemed_by_order_id,
            ],
            'expiration' => [
                'expires_at' => $this->expires_at?->toISOString(),
                'days_remaining' => $this->expires_at ? now()->diffInDays($this->expires_at, false) : null,
            ],
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
        ];
    }
} 