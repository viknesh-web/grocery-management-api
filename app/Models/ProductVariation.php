<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'unit',
        'price',
        'stock_quantity',
        'sku',
        'enabled',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'enabled' => 'boolean',
    ];

    protected $appends = [
        'display_name',
        'full_name',
    ];

    /**
     * Get the product that owns this variation.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the display name for this variation (e.g., "100 gm" or "1 kg").
     * Converts gm to kg if >= 1000, ml to liter if >= 1000.
     */
    public function getDisplayNameAttribute(): string
    {
        $quantity = (float) $this->quantity;
        $unit = $this->unit;

        // Convert gm to kg if >= 1000
        if ($unit === 'gm' && $quantity >= 1000) {
            $quantity = $quantity / 1000;
            $unit = 'kg';
        }

        // Convert ml to liter if >= 1000
        if ($unit === 'ml' && $quantity >= 1000) {
            $quantity = $quantity / 1000;
            $unit = 'liter';
        }

        // Format the display name
        if ($quantity == (int) $quantity) {
            // Whole number, display without decimals
            return (int) $quantity . ' ' . $unit;
        }

        return number_format($quantity, 2, '.', '') . ' ' . $unit;
    }

    /**
     * Get the full name combining product name and variation (e.g., "Carrot - 1 kg").
     */
    public function getFullNameAttribute(): string
    {
        return $this->product->name . ' - ' . $this->display_name;
    }

    /**
     * Check if this variation is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Scope a query to only include enabled variations.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to only include in-stock variations.
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }
}
