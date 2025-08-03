<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'restaurant_id' => $this->restaurant_id,
            'restaurant_branch_id' => $this->restaurant_branch_id,
            'permissions' => $this->permissions,
            'phone' => $this->phone,
            'last_login_at' => $this->last_login_at,
            'is_email_verified' => $this->is_email_verified,
            'profile_image_url' => $this->profile_image_url,
            'is_mfa_enabled' => $this->is_mfa_enabled,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
