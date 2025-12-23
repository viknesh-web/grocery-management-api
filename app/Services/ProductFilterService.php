<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ProductFilterService
{
    /**
     * Apply all filters to the query.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query = $this->applySearch($query, $filters['search']);
        }

        // Category filter
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Product type filter
        if (isset($filters['product_type']) && in_array($filters['product_type'], ['daily', 'standard'])) {
            $query->where('product_type', $filters['product_type']);
        }

        // Status filter
        if (isset($filters['status'])) {
            $enabled = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $query->where('enabled', $enabled);
        } elseif (isset($filters['enabled'])) {
            $enabled = filter_var($filters['enabled'], FILTER_VALIDATE_BOOLEAN);
            $query->where('enabled', $enabled);
        }

        // Has discount filter (date-range aware)
        if (isset($filters['has_discount'])) {
            $hasDiscount = filter_var($filters['has_discount'], FILTER_VALIDATE_BOOLEAN);
            $today = now()->toDateString();

            if ($hasDiscount) {
                $query->where('discount_type', '!=', 'none')
                    ->whereNotNull('discount_value')
                    ->where('discount_value', '>', 0)
                    ->where(function ($q) use ($today) {
                        $q->where(function ($q2) {
                            // No date range configured - always active
                            $q2->whereNull('discount_start_date')
                                ->whereNull('discount_end_date');
                        })->orWhere(function ($q2) use ($today) {
                            // Start only
                            $q2->whereNotNull('discount_start_date')
                                ->whereNull('discount_end_date')
                                ->where('discount_start_date', '<=', $today);
                        })->orWhere(function ($q2) use ($today) {
                            // End only
                            $q2->whereNull('discount_start_date')
                                ->whereNotNull('discount_end_date')
                                ->where('discount_end_date', '>=', $today);
                        })->orWhere(function ($q2) use ($today) {
                            // Start and end
                            $q2->whereNotNull('discount_start_date')
                                ->whereNotNull('discount_end_date')
                                ->where('discount_start_date', '<=', $today)
                                ->where('discount_end_date', '>=', $today);
                        });
                    });
            } else {
                $query->where(function ($q) use ($today) {
                    $q->where('discount_type', 'none')
                        ->orWhereNull('discount_value')
                        ->orWhere('discount_value', '<=', 0)
                        // Or discount exists but is outside the active date range
                        ->orWhere(function ($q2) use ($today) {
                            $q2->where('discount_type', '!=', 'none')
                                ->whereNotNull('discount_value')
                                ->where('discount_value', '>', 0)
                                ->where(function ($q3) use ($today) {
                                    $q3->where(function ($qq) use ($today) {
                                        // Starts in future
                                        $qq->whereNotNull('discount_start_date')
                                            ->where('discount_start_date', '>', $today);
                                    })->orWhere(function ($qq) use ($today) {
                                        // Ended in past
                                        $qq->whereNotNull('discount_end_date')
                                            ->where('discount_end_date', '<', $today);
                                    });
                                });
                        });
                });
            }
        }

        // Stock status filter
        if (isset($filters['stock_status'])) {
            $query = $this->applyStockStatusFilter($query, $filters['stock_status']);
        }

        return $query;
    }

    /**
     * Apply search to the query.
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return Builder
     */
    public function applySearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
                ->orWhere('item_code', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Apply stock status filter.
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function applyStockStatusFilter(Builder $query, string $status): Builder
    {
        switch ($status) {
            case 'in_stock':
                return $query->where('stock_quantity', '>', 10);
            case 'low_stock':
                return $query->where('stock_quantity', '>', 0)
                    ->where('stock_quantity', '<=', 10);
            case 'out_of_stock':
                return $query->where('stock_quantity', '<=', 0);
            default:
                return $query;
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param Builder $query
     * @param string|null $sortBy
     * @param string $sortOrder
     * @return Builder
     */
    public function applySorting(Builder $query, ?string $sortBy, string $sortOrder = 'asc'): Builder
    {
        $allowedSortFields = ['name', 'original_price', 'selling_price', 'stock_quantity', 'created_at', 'product_type'];
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        if ($sortBy && in_array($sortBy, $allowedSortFields)) {
            // For calculated fields, we need special handling
            if ($sortBy === 'selling_price') {
                // Sort by original_price and discount for selling_price approximation
                $query->orderBy('original_price', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Secondary sort by name for consistency
        if ($sortBy !== 'name') {
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    /**
     * Get filter metadata.
     *
     * @param Builder $query
     * @param array $filters
     * @return array
     */
    public function getFilterMetadata(Builder $query, array $filters): array
    {
        $baseQuery = clone $query;

        return [
            'total_count' => $baseQuery->count(),
            'filters_applied' => array_filter($filters, fn($value) => !empty($value)),
        ];
    }
}


