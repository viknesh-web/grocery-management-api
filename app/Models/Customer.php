<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'whatsapp_number',
        'address',
        'email',
        'landmark',
        'remarks',
        'status',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Scope: Apply multiple filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('whatsapp_number', 'like', "%{$search}%")
                          ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            });
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Inactive customers
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope a query to search customers by name, phone, or address.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('whatsapp_number', 'like', "%{$search}%")
              ->orWhere('address', 'like', "%{$search}%")
              ->orWhere('landmark', 'like', "%{$search}%");
        });
    }

    /**
     * Scope: By location/landmark
     */
    public function scopeByLandmark(Builder $query, string $landmark): Builder
    {
        return $query->where('landmark', 'like', "%{$landmark}%");
    }

    /**
     * Scope: Customers with remarks
     */
    public function scopeHasRemarks(Builder $query): Builder
    {
        return $query->whereNotNull('remarks')
                     ->where('remarks', '!=', '');
    }

    /**
     * Scope: Sort by name
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name', 'asc');
    }
}
