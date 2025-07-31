<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

final class Driver extends Model
{
    use HasFactory, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'date_of_birth',
        'national_id',
        'driver_license_number',
        'license_expiry_date',
        'profile_image_url',
        'license_image_url',
        'vehicle_type',
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_plate_number',
        'vehicle_image_url',
        'status',
        'is_online',
        'is_available',
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'rating',
        'total_deliveries',
        'completed_deliveries',
        'cancelled_deliveries',
        'total_earnings',
        'email_verified_at',
        'phone_verified_at',
        'verified_at',
        'last_active_at',
        'documents',
        'banking_info',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'national_id',
        'driver_license_number',
        'banking_info',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'license_expiry_date' => 'date',
            'is_online' => 'boolean',
            'is_available' => 'boolean',
            'current_latitude' => 'decimal:8',
            'current_longitude' => 'decimal:8',
            'last_location_update' => 'datetime',
            'rating' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'documents' => 'array',
            'banking_info' => 'encrypted:array', // Encrypt sensitive banking info
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the driver working zones for the driver.
     */
    public function workingZones(): HasMany
    {
        return $this->hasMany(DriverWorkingZone::class);
    }

    /**
     * Get the order assignments for the driver.
     */
    public function orderAssignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class);
    }

    /**
     * Get the delivery reviews for the driver.
     */
    public function deliveryReviews(): HasMany
    {
        return $this->hasMany(DeliveryReview::class);
    }

    /**
     * Scope a query to only include active drivers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include online drivers.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope a query to only include available drivers.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Get the coordinates as an array.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => $this->current_latitude,
            'longitude' => $this->current_longitude
        ];
    }

    /**
     * Set the coordinates from an array.
     */
    public function setCoordinatesAttribute(array $coordinates): void
    {
        $this->current_latitude = $coordinates['latitude'] ?? null;
        $this->current_longitude = $coordinates['longitude'] ?? null;
    }
}
