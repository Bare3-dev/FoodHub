<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class RestaurantBranch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'name',
        'slug',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'phone',
        'manager_name',
        'manager_phone',
        'operating_hours',
        'delivery_zones',
        'delivery_fee',
        'minimum_order_amount',
        'estimated_delivery_time',
        'status',
        'accepts_online_orders',
        'accepts_delivery',
        'accepts_pickup',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
            'delivery_zones' => 'array',
            'settings' => 'array',
            'delivery_fee' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'accepts_online_orders' => 'boolean',
            'accepts_delivery' => 'boolean',
            'accepts_pickup' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant that owns the branch.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the users (staff) for the branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'restaurant_branch_id');
    }

    /**
     * Get the menu items for the branch (through branch_menu_items pivot).
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(BranchMenuItem::class);
    }

    /**
     * Get the orders for the branch.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'restaurant_branch_id');
    }

    /**
     * Scope a query to only include active branches.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include branches that accept online orders.
     */
    public function scopeAcceptsOnlineOrders($query)
    {
        return $query->where('accepts_online_orders', true);
    }

    /**
     * Scope a query to only include branches within a certain radius (example for geospatial queries).
     * Requires PostGIS extension on PostgreSQL and proper indexing.
     */
    public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm)
    {
        // This assumes you have a PostGIS enabled column or are using a geometry type for latitude/longitude
        // For our current setup with decimal lat/long, this is a simplified distance check.
        // A proper PostGIS query would use ST_DWithin or similar functions.
        $haversineSelect = "(6371 * acos(cos(radians({$latitude})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$longitude})) + sin(radians({$latitude})) * sin(radians(latitude))))";
        $haversineWhere = "(6371 * acos(cos(radians({$latitude})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$longitude})) + sin(radians({$latitude})) * sin(radians(latitude))))";

        return $query->select(DB::raw("*, {$haversineSelect} AS distance"))
            ->whereRaw("{$haversineWhere} < ?", [$radiusKm])
            ->orderBy('distance');
    }
}
