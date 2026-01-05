<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Product extends Model
{
    use HasFactory;
   
    public const UNIT_CONVERSIONS = [
        // Weight units
        'weight' => [
            'base' => 'kg',
            'conversions' => [
                'kg' => 1,
                'gram' => 1000,
                // 'mg' => 1000000,
                // 'milligram' => 1000000,
            ],
        ],
        
        // Volume units
        'volume' => [
            'base' => 'l',
            'conversions' => [               
                'liter' => 1,
                'ml' => 1000,
            ],
        ],
        
        // Count units
        'count' => [
            'base' => 'piece',
            'conversions' => [
                'piece' => 1,
                'pack' => 1,
            ],
        ],
    ];

    /**
     * Unit aliases for normalization
     */
    public const UNIT_ALIASES = [
        'kilo' => 'kg',
        'kilos' => 'kg',
        'ltrs' => 'ltr',
        'liters' => 'ltr',
        'litres' => 'ltr',
    ];

    protected $fillable = [
        'name',
        'item_code',
        'category_id',
        'image',
        'regular_price',
        'stock_quantity',
        'stock_unit',
        'min_quantity',
        'max_quantity',
        'status',
        'product_type',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'max_quantity' => 'decimal:2',
    ];

    protected $appends = [
        'image_url',
        'selling_price',
        'discount_amount',
        'discount_percentage',
        'has_discount',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * Get the category that this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    // ===== DISCOUNT METHODS (EXISTING - UNCHANGED) =====

    /**
     * Get active discount for this product.
     * 
     * Returns the first active discount that is within date range.
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
     * Determine if the discount is currently active based on date range.
     */
    public function isDiscountActive(?Carbon $now = null): bool
    {
        return $this->activeDiscount() !== null;
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

        return asset('assets/images/no-image.png');
    }

    /**
     * Calculate selling price based on discount.
     * 
     * UNCHANGED - Uses existing ProductDiscount logic
     */
    public function getSellingPriceAttribute(): float
    {
        $regularPrice = (float) $this->regular_price;
        $discount = $this->activeDiscount();

        if (!$discount) {
            return $regularPrice;
        }

        return $discount->calculateSellingPrice($regularPrice);
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

        return $discount->calculateDiscountAmount((float) $this->regular_price);
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

    // ===== UNIT CONVERSION METHODS (NEW) =====

    /**
     * Get effective price (considers discount).
     * 
     * Alias for selling_price attribute.
     * Used by PriceCalculator for consistency.
     *
     * @return float
     */
    public function getEffectivePrice(): float
    {
        return $this->selling_price;
    }

    /**
     * Get available units for this product based on its stock_unit.
     * 
     * Example: If stock_unit is 'kg', returns ['kg', 'g', 'mg']
     *
     * @return array
     */
    public function getAvailableUnits(): array
    {
        $baseUnit = $this->normalizeUnit($this->stock_unit);
        
        foreach (self::UNIT_CONVERSIONS as $family => $data) {
            if (isset($data['conversions'][$baseUnit])) {
                return array_keys($data['conversions']);
            }
        }
        
        // If no conversion family found, return just the base unit
        return [$baseUnit];
    }

    /**
     * Calculate price for given quantity and unit.
     * 
     * Examples:
     * - Product: Sugar 1kg = AED 100
     * - Customer orders: 500g → Price = AED 50
     * - Customer orders: 2kg → Price = AED 200
     *
     * @param float $quantity
     * @param string $unit
     * @return float
     * @throws \InvalidArgumentException If unit conversion not possible
     */
    public function calculatePriceForQuantity(float $quantity, string $unit): float
    {
        $baseUnit = $this->normalizeUnit($this->stock_unit);
        $requestedUnit = $this->normalizeUnit($unit);
        
        // Get effective price (with discount)
        $basePrice = $this->getEffectivePrice();
        
        // If same unit, direct calculation
        if ($baseUnit === $requestedUnit) {
            return round($basePrice * $quantity, 2);
        }
        
        // Get conversion factor
        $conversionFactor = $this->getUnitConversionFactor($baseUnit, $requestedUnit);
        
        if ($conversionFactor === null) {
            throw new \InvalidArgumentException(
                "Cannot convert from '{$requestedUnit}' to '{$baseUnit}' for product: {$this->name}"
            );
        }
        
        // Convert quantity to base unit
        // Example: 500g to kg → 500 / 1000 = 0.5 kg
        $quantityInBaseUnit = $quantity / $conversionFactor;
        
        return round($basePrice * $quantityInBaseUnit, 2);
    }

    /**
     * Normalize unit name (handle aliases and case).
     *
     * @param string $unit
     * @return string
     */
    private function normalizeUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        
        // Handle aliases
        if (isset(self::UNIT_ALIASES[$unit])) {
            return self::UNIT_ALIASES[$unit];
        }
        
        return $unit;
    }

    /**
     * Get conversion factor between two units.
     * 
     * Returns the multiplier to convert from target unit to base unit.
     * Example: kg to g → 1000 (because 1 kg = 1000 g)
     *
     * @param string $baseUnit
     * @param string $targetUnit
     * @return float|null Null if conversion not possible
     */
    private function getUnitConversionFactor(string $baseUnit, string $targetUnit): ?float
    {
        foreach (self::UNIT_CONVERSIONS as $family => $data) {
            if (isset($data['conversions'][$baseUnit]) && 
                isset($data['conversions'][$targetUnit])) {
                
                $baseValue = $data['conversions'][$baseUnit];
                $targetValue = $data['conversions'][$targetUnit];
                
                // Calculate conversion factor
                // Example: kg to g → 1000 / 1 = 1000
                return $targetValue / $baseValue;
            }
        }
        
        return null;
    }

    /**
     * Check if unit conversion is possible.
     *
     * @param string $fromUnit
     * @param string $toUnit
     * @return bool
     */
    public function canConvertUnit(string $fromUnit, string $toUnit): bool
    {
        $from = $this->normalizeUnit($fromUnit);
        $to = $this->normalizeUnit($toUnit);
        
        return $this->getUnitConversionFactor($from, $to) !== null;
    }

    /**
     * Get unit family name (weight, volume, count).
     *
     * @param string|null $unit
     * @return string|null
     */
    public function getUnitFamily(?string $unit = null): ?string
    {
        $unit = $this->normalizeUnit($unit ?? $this->stock_unit);
        
        foreach (self::UNIT_CONVERSIONS as $family => $data) {
            if (isset($data['conversions'][$unit])) {
                return $family;
            }
        }
        
        return null;
    }

    /**
     * Format quantity with unit for display.
     *
     * @param float $quantity
     * @param string $unit
     * @return string
     */
    public function formatQuantityWithUnit(float $quantity, string $unit): string
    {
        // Remove unnecessary decimals
        $qty = $quantity == floor($quantity) ? (int) $quantity : $quantity;
        
        return "{$qty} {$unit}";
    }

    // ===== SCOPES (EXISTING - UNCHANGED) =====

    /**
     * Scope a query to only include active products.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to search products by name or item code.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('item_code', 'like', "%{$search}%");
        });
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to filter by product type.
     */
    public function scopeByProductType(Builder $query, string $type): Builder
    {
        return $query->where('product_type', $type);
    }

    /**
     * Scope a query to only include daily products.
     */
    public function scopeDaily(Builder $query): Builder
    {
        return $query->where('product_type', 'daily');
    }

    /**
     * Scope a query to only include standard products.
     */
    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('product_type', 'standard');
    }

    /**
     * Scope a query to filter products with active discount.
     */
    public function scopeWithDiscount(Builder $query): Builder
    {
        return $this->scopeWithActiveDiscount($query);
    }

    /**
     * Scope a query to filter products without active discount.
     */
    public function scopeWithoutDiscount(Builder $query): Builder
    {
        return $this->scopeWithoutActiveDiscount($query);
    }

    /**
     * Scope a query to filter products with active discount (handles date-range logic).
     */
    public function scopeWithActiveDiscount(Builder $query, ?Carbon $date = null): Builder
    {
        $now = $date ?? Carbon::now();
        
        return $query->whereHas('discounts', function ($q) use ($now) {
            $q->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $now);
                });
        });
    }

    /**
     * Scope a query to filter products without active discount.
     */
    public function scopeWithoutActiveDiscount(Builder $query, ?Carbon $date = null): Builder
    {
        $now = $date ?? Carbon::now();
        
        return $query->whereDoesntHave('discounts', function ($q) use ($now) {
            $q->where('status', 'active')
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $now);
                });
        });
    }

    /**
     * Scope a query to filter by stock status.
     */
    public function scopeStockStatus(Builder $query, string $status): Builder
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
     * Scope: Apply multiple filters at once
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('item_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, function ($q, $categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['product_type'] ?? null, function ($q, $type) {
                $q->where('product_type', $type);
            })
            ->when(isset($filters['has_discount']), function ($q) use ($filters) {
                return filter_var($filters['has_discount'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->withActiveDiscount()
                    : $q->withoutActiveDiscount();
            })
            ->when($filters['stock_status'] ?? null, function ($q, $status) {
                $q->stockStatus($status);
            });
    }

    /**
     * Scope: Active products only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Inactive products
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope: Products in stock (quantity > 0)
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope: Products out of stock
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    /**
     * Scope: Products with low stock (0 < quantity <= 10)
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0)
                     ->where('stock_quantity', '<=', 10);
    }

    /**
     * Scope: Sort by common fields
     */
    public function scopeSortBy(Builder $query, string $field = 'created_at', string $direction = 'desc'): Builder
    {
        $allowedFields = [
            'name', 
            'regular_price', 
            'stock_quantity', 
            'created_at', 
            'updated_at'
        ];

        if (!in_array($field, $allowedFields)) {
            $field = 'created_at';
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($field, $direction);
    }

    /**
     * Scope: With common relationships
     */
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'category:id,name,image',
        ]);
    }

    /**
     * Get stock status as string
     */
    public function getStockStatusAttribute(): string
    {
        return $this->stock_quantity > 10 ? 'in_stock' : 
               ($this->stock_quantity > 0 ? 'low_stock' : 'out_of_stock');
    }
}