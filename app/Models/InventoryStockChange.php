<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryStockChange extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_changes';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'branch_menu_item_id',
        'user_id',
        'change_type',
        'quantity_change',
        'previous_quantity',
        'new_quantity',
        'reason',
        'metadata',
        'source',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the branch menu item that owns the stock change.
     */
    public function branchMenuItem(): BelongsTo
    {
        return $this->belongsTo(BranchMenuItem::class);
    }

    /**
     * Get the user who made the stock change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include changes of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('change_type', $type);
    }

    /**
     * Scope a query to only include changes from a specific source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Get the change direction (positive for additions, negative for reductions).
     */
    public function getChangeDirection(): string
    {
        return $this->quantity_change > 0 ? 'addition' : 'reduction';
    }

    /**
     * Get the absolute change amount.
     */
    public function getAbsoluteChange(): int
    {
        return abs($this->quantity_change);
    }
}
