<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'item_code',
        'category_id',
        'image',
        'original_price',
        'discount_type',
        'discount_value',
        'discount_start_date',
        'discount_end_date',
        'stock_quantity',
        'stock_unit',
        'enabled',
        'product_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'enabled' => 'boolean',
        'discount_start_date' => 'date',
        'discount_end_date' => 'date',
    ];

    protected $appends = [
        'image_url',
        'selling_price',
        'discount_amount',
        'discount_percentage',
        'has_discount',
    ];

    /**
     * Determine if the discount is currently active based on date range.
     */
    public function isDiscountActive(?Carbon $now = null): bool
    {
        // No discount configured
        if ($this->discount_type === 'none' || !$this->discount_value) {
            return false;
        }

        $now = $now ?: Carbon::now();

        // If no date range configured, treat discount as always active (backwards compatible)
        if (!$this->discount_start_date && !$this->discount_end_date) {
            return true;
        }

        $start = $this->discount_start_date ? Carbon::parse($this->discount_start_date)->startOfDay() : null;
        $end = $this->discount_end_date ? Carbon::parse($this->discount_end_date)->endOfDay() : null;

        if ($start && $now->lt($start)) {
            return false;
        }

        if ($end && $now->gt($end)) {
            return false;
        }

        return true;
    }

    /**
     * Get the category that this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created the product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the product.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all price updates for this product.
     */
    public function priceUpdates(): HasMany
    {
        return $this->hasMany(PriceUpdate::class);
    }

    /**
     * Get all variations for this product.
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * Get only active (enabled) variations for this product.
     */
    public function activeVariations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->where('enabled', true);
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        $imagePath = ltrim($this->image, '/');

        if ($imagePath && Storage::disk('media')->exists($imagePath)) {
            return Storage::disk('media')->url($imagePath);
        }

        return  asset('assets/images/no-image.png');
    }

    /**
     * Calculate selling price based on discount.
     */
    public function getSellingPriceAttribute(): float
    {
        $originalPrice = (float) $this->original_price;

        if (!$this->isDiscountActive()) {
            return $originalPrice;
        }

        if ($this->discount_type === 'percentage') {
            $discountAmount = $originalPrice * ((float) $this->discount_value / 100);
            $sellingPrice = $originalPrice - $discountAmount;
        } else { // fixed
            $sellingPrice = $originalPrice - (float) $this->discount_value;
        }

        return max(0, round($sellingPrice, 2));
    }

    /**
     * Get discount amount in rupees.
     */
    public function getDiscountAmountAttribute(): float
    {
        if (!$this->isDiscountActive()) {
            return 0;
        }

        $originalPrice = (float) $this->original_price;

        if ($this->discount_type === 'percentage') {
            return round($originalPrice * ((float) $this->discount_value / 100), 2);
        }

        return round((float) $this->discount_value, 2);
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentageAttribute(): float
    {
        if (!$this->isDiscountActive()) {
            return 0;
        }

        $originalPrice = (float) $this->original_price;

        if ($originalPrice == 0) {
            return 0;
        }

        if ($this->discount_type === 'percentage') {
            return round((float) $this->discount_value, 2);
        }

        // Calculate percentage from fixed discount
        return round(($this->discount_amount / $originalPrice) * 100, 2);
    }

    /**
     * Check if product has discount.
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->isDiscountActive();
    }

    /**
     * Scope a query to only include enabled products.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to search products by name or item code.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to filter by product type.
     */
    public function scopeByProductType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    /**
     * Scope a query to only include daily products.
     */
    public function scopeDaily($query)
    {
        return $query->where('product_type', 'daily');
    }

    /**
     * Scope a query to only include standard products.
     */
    public function scopeStandard($query)
    {
        return $query->where('product_type', 'standard');
    }

    /**
     * Scope a query to filter products with discount.
     */
    public function scopeWithDiscount($query)
    {
        return $query->where('discount_type', '!=', 'none')
            ->whereNotNull('discount_value')
            ->where('discount_value', '>', 0);
    }

    /**
     * Scope a query to filter products without discount.
     */
    public function scopeWithoutDiscount($query)
    {
        return $query->where(function ($q) {
            $q->where('discount_type', 'none')
                ->orWhereNull('discount_value')
                ->orWhere('discount_value', '<=', 0);
        });
    }

    /**
     * Scope a query to filter by stock status.
     */
    public function scopeStockStatus($query, string $status)
    {
        return match ($status) {
            'in_stock' => $query->where('stock_quantity', '>', 10),
            'low_stock' => $query->where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', 10),
            'out_of_stock' => $query->where('stock_quantity', '<=', 0),
            default => $query,
        };
    }

    /**
     * Check if product has variations.
     */
    public function hasVariations(): bool
    {
        return $this->variations()->exists();
    }

    /**
     * Get the cheapest variation for this product.
     */
    public function getCheapestVariation(): ?ProductVariation
    {
        return $this->activeVariations()->orderBy('price', 'asc')->first();
    }

    /**
     * Get price range accessor (e.g., "₹5.00 - ₹35.00" or single price if all same).
     */
    public function getPriceRangeAttribute(): ?string
    {
        $variations = $this->activeVariations()->get();
        
        if ($variations->isEmpty()) {
            // Fallback to original_price if no variations
            return '₹' . number_format((float) $this->original_price, 2);
        }

        $prices = $variations->pluck('price')->map(fn($price) => (float) $price);
        $minPrice = $prices->min();
        $maxPrice = $prices->max();

        if ($minPrice === $maxPrice) {
            return '₹' . number_format($minPrice, 2);
        }

        return '₹' . number_format($minPrice, 2) . ' - ₹' . number_format($maxPrice, 2);
    }
}
