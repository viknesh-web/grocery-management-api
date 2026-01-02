<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Product Filter Service
 * 
 * @deprecated This service is deprecated. Product filtering is now handled by Product model scopes.
 * Use ProductRepository methods which internally use Product::filter() and Product::sortBy() scopes.
 * 
 * This service is kept for backward compatibility but should not be used in new code.
 * 
 * Migration path:
 * - Instead of: $filterService->applyFilters($query, $filters)
 * - Use: $productRepository->all($filters) or $productRepository->paginate($filters)
 * - Or directly: Product::filter($filters)->sortBy($sortBy, $sortOrder)
 */
class ProductFilterService
{
    /**
     * Apply all filters to the query.
     * 
     * @deprecated Use Product::filter($filters) scope instead
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Delegate to Product model scopeFilter() for consistency
        return $query->filter($filters);
    }

    /**
     * Apply search to the query.
     * 
     * @deprecated Use Product::filter(['search' => $term]) instead
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return Builder
     */
    public function applySearch(Builder $query, string $searchTerm): Builder
    {
        // Delegate to Product model scopeFilter() for consistency
        return $query->filter(['search' => $searchTerm]);
    }

    /**
     * Apply stock status filter.
     * 
     * @deprecated Use Product::filter(['stock_status' => $status]) instead
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function applyStockStatusFilter(Builder $query, string $status): Builder
    {
        // Delegate to Product model scopeStockStatus() for consistency
        return $query->stockStatus($status);
    }

    /**
     * Apply sorting to the query.
     * 
     * @deprecated Use Product::sortBy($sortBy, $sortOrder) scope instead
     *
     * @param Builder $query
     * @param string|null $sortBy
     * @param string $sortOrder
     * @return Builder
     */
    public function applySorting(Builder $query, ?string $sortBy, string $sortOrder = 'asc'): Builder
    {
        // Delegate to Product model scopeSortBy() for consistency
        return $query->sortBy($sortBy ?? 'created_at', $sortOrder);
    }

    /**
     * Get filter metadata.
     * 
     * @deprecated This method is no longer needed. Use repository methods for metadata.
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
