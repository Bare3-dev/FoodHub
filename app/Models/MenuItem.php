<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MenuItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'menu_category_id',
        'name',
        'slug',
        'description',
        'ingredients',
        'price',
        'cost_price',
        'currency',
        'sku',
        'images',
        'preparation_time',
        'calories',
        'nutritional_info',
        'allergens',
        'dietary_tags',
        'is_available',
        'is_featured',
        'is_spicy',
        'spice_level',
        'sort_order',
        'customization_options',
        'pos_data',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'images' => 'array',
            'nutritional_info' => 'array',
            'allergens' => 'array',
            'dietary_tags' => 'array',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_spicy' => 'boolean',
            'customization_options' => 'array',
            'pos_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the restaurant that owns the menu item.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the menu category that the menu item belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * Alias for menu category relationship for resource/controller eager loading.
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * Get the branch menu items for this menu item.
     */
    public function branchMenuItems(): HasMany
    {
        return $this->hasMany(BranchMenuItem::class);
    }

    /**
     * Scope a query to only include available menu items.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
