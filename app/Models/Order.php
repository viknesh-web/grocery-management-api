<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'order_date',
        'delivery_date',
        'subtotal',
        'discount_amount',
        'total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => 'string',
    ];

    /**
     * Get the customer that owns this order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the order items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the user who created this order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this order.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Apply multiple filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%")
                                ->orWhere('whatsapp_number', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('address', 'like', "%{$search}%");
                          });
                });
            })
            
            ->when($filters['customer_address'] ?? null, function ($q, $address) {
                $q->whereHas('customer', function ($query) use ($address) {
                    $query->where('address', 'like', "%{$address}%");
                });
            })
            
            ->when($filters['status'] ?? null, fn($q, $status) => 
                $q->where('status', $status))
            
            ->when($filters['customer_ids'] ?? null, function ($q, $ids) {
                if (is_array($ids) && !empty($ids)) {
                    $q->whereIn('customer_id', $ids);
                }
            })
            
            ->when($filters['date_from'] ?? null, fn($q, $date) => 
                $q->whereDate('order_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => 
                $q->whereDate('order_date', '<=', $date));
    }

    /**
     * Scope: Recent orders
     */
    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Pending orders
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Completed orders
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

     /**
     * Scope: Cancelled orders
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Orders by date range
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Today's orders
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('order_date', today());
    }
    /**
     * Scope: This week's orders
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope: This month's orders
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year);
    }
}

