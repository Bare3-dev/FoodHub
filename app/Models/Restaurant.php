<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

final class Restaurant extends Model
{
    use HasFactory, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cuisine_type',
        'phone',
        'email',
        'website',
        'logo_url',
        'cover_image_url',
        'business_hours',
        'settings',
        'status',
        'commission_rate',
        'is_featured',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'settings' => 'array',
            'commission_rate' => 'decimal:2',
            'is_featured' => 'boolean',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant branches for the restaurant.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(RestaurantBranch::class);
    }

    /**
     * Get the users (staff) for the restaurant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the menu categories for the restaurant.
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * Get the menu items for the restaurant.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Get the orders for the restaurant.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the loyalty programs for the restaurant.
     */
    public function loyaltyPrograms(): HasMany
    {
        return $this->hasMany(LoyaltyProgram::class);
    }

    /**
     * Get the customer feedback for this restaurant.
     */
    public function customerFeedback(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerFeedback::class);
    }

    /**
     * Scope a query to only include active restaurants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include featured restaurants.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include verified restaurants.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }
}
