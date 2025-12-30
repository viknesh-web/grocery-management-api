<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PriceUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'old_regular_price',
        'new_regular_price',
        'old_discount_type',
        'new_discount_type',
        'old_discount_value',
        'new_discount_value',
        'old_stock_quantity',
        'new_stock_quantity',
        'old_selling_price',
        'new_selling_price',
        'updated_by',
    ];

    protected $casts = [
        'old_regular_price' => 'decimal:2',
        'new_regular_price' => 'decimal:2',
        'old_discount_value' => 'decimal:2',
        'new_discount_value' => 'decimal:2',
        'old_stock_quantity' => 'decimal:2',
        'new_stock_quantity' => 'decimal:2',
        'old_selling_price' => 'decimal:2',
        'new_selling_price' => 'decimal:2',
    ];

    protected $appends = ['price_change_percentage'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getPriceChangePercentageAttribute(): ?float
    {
        // Use stored selling prices if available, otherwise calculate
        $oldSellingPrice = $this->old_selling_price;
        $newSellingPrice = $this->new_selling_price;

        // If old_selling_price not stored, calculate from old_regular_price
        if ($oldSellingPrice === null && $this->old_regular_price) {
            $oldSellingPrice = $this->old_regular_price;
            if ($this->old_discount_type === 'percentage' && $this->old_discount_value !== null) {
                $oldSellingPrice -= ($this->old_regular_price * $this->old_discount_value / 100);
            } elseif ($this->old_discount_type === 'fixed' && $this->old_discount_value !== null) {
                $oldSellingPrice -= $this->old_discount_value;
            }
            $oldSellingPrice = max(0, round($oldSellingPrice, 2));
        }

        // If new_selling_price not stored, calculate from new_regular_price
        if ($newSellingPrice === null && $this->new_regular_price) {
            $newSellingPrice = $this->new_regular_price;
            if ($this->new_discount_type === 'percentage' && $this->new_discount_value !== null) {
                $newSellingPrice -= ($this->new_regular_price * $this->new_discount_value / 100);
            } elseif ($this->new_discount_type === 'fixed' && $this->new_discount_value !== null) {
                $newSellingPrice -= $this->new_discount_value;
            }
            $newSellingPrice = max(0, round($newSellingPrice, 2));
        }

        if (!$oldSellingPrice || $oldSellingPrice == 0) {
            return null; // Avoid division by zero
        }

        $change = $newSellingPrice - $oldSellingPrice;
        return round(($change / $oldSellingPrice) * 100, 2);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        return $query->whereBetween('created_at', [$start, $end]);
    }
}
