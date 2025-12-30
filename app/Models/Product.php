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
        'regular_price',
        'stock_quantity',
        'stock_unit',
        'status',
        'product_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
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
        $activeDiscount = $this->discounts()
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $now = $now ?: Carbon::now();
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $now);
                    });
            })
            ->first();

        return $activeDiscount !== null;
    }

    /**
     * Get the category that this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created this product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this product.
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
     * Get all discounts for this product.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class);
    }


    /**
     * Get active discount for this product.
     */
    public function activeDiscount(): ?ProductDiscount
    {
        $now = Carbon::now();
        return $this->discounts()
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $now);
                    });
            })
            ->first();
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
        $regularPrice = (float) $this->regular_price;
        $discount = $this->activeDiscount();

        if (!$discount) {
            return $regularPrice;
        }

        if ($discount->discount_type === 'percentage') {
            $discountAmount = $regularPrice * ((float) $discount->discount_value / 100);
            $sellingPrice = $regularPrice - $discountAmount;
        } else { // fixed
            $sellingPrice = $regularPrice - (float) $discount->discount_value;
        }

        return max(0, round($sellingPrice, 2));
    }

    /**
     * Get discount amount in rupees.
     */
    public function getDiscountAmountAttribute(): float
    {
        $discount = $this->activeDiscount();
        
        if (!$discount) {
            return 0;
        }

        $regularPrice = (float) $this->regular_price;

        if ($discount->discount_type === 'percentage') {
            return round($regularPrice * ((float) $discount->discount_value / 100), 2);
        }

        return round((float) $discount->discount_value, 2);
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentageAttribute(): float
    {
        $discount = $this->activeDiscount();
        
        if (!$discount) {
            return 0;
        }

        $regularPrice = (float) $this->regular_price;

        if ($regularPrice == 0) {
            return 0;
        }

        if ($discount->discount_type === 'percentage') {
            return round((float) $discount->discount_value, 2);
        }

        // Calculate percentage from fixed discount
        return round(($this->discount_amount / $regularPrice) * 100, 2);
    }

    /**
     * Check if product has discount.
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->isDiscountActive();
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 'active');
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
     * Scope a query to filter products with active discount.
     */
    public function scopeWithDiscount($query)
    {
        $now = Carbon::now();
        return $query->whereHas('discounts', function ($q) use ($now) {
            $q->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', $now)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', $now);
                        });
                });
        });
    }

    /**
     * Scope a query to filter products without active discount.
     */
    public function scopeWithoutDiscount($query)
    {
        $now = Carbon::now();
        return $query->whereDoesntHave('discounts', function ($q) use ($now) {
            $q->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', $now)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', $now);
                        });
                });
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

}
