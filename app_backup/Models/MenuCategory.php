<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class MenuCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'restaurant_id',
        'parent_category_id',
        'name',
        'slug',
        'description',
        'image_url',
        'sort_order',
        'is_active',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MenuCategory $menuCategory): void {
            $baseSlug = Str::slug($menuCategory->name);
            $slug = $baseSlug;
            $counter = 1;

            while (self::where('slug', $slug)
                ->where('restaurant_id', $menuCategory->restaurant_id)
                ->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $menuCategory->slug = $slug;
        });
    }

    /**
     * Get the restaurant that owns the menu category.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the parent category that owns this menu category.
     */
    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'parent_category_id');
    }

    /**
     * Get the subcategories for the menu category.
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'parent_category_id');
    }

    /**
     * Get the menu items for the menu category.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_category_id');
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
