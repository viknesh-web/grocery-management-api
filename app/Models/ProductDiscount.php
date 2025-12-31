<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ProductDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'status',
    ];

    protected $hidden = [
        'product_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the product that owns this discount.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if discount is currently active based on status and date range.
     */
    public function isActive(?Carbon $now = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = $now ?: Carbon::now();

        // If no date range configured, treat discount as always active
        if (!$this->start_date && !$this->end_date) {
            return true;
        }

        $start = $this->start_date ? Carbon::parse($this->start_date)->startOfDay() : null;
        $end = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;

        if ($start && $now->lt($start)) {
            return false;
        }

        if ($end && $now->gt($end)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given price.
     */
    public function calculateDiscountAmount(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            return round($price * ((float) $this->discount_value / 100), 2);
        }

        return round((float) $this->discount_value, 2);
    }

    /**
     * Calculate selling price after discount.
     */
    public function calculateSellingPrice(float $regularPrice): float
    {
        $discountAmount = $this->calculateDiscountAmount($regularPrice);
        return max(0, round($regularPrice - $discountAmount, 2));
    }

    /**
     * Scope a query to only include active discounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter discounts active on a specific date.
     */
    public function scopeActiveOn($query, Carbon $date)
    {
        return $query->where('status', 'active')
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }
}




