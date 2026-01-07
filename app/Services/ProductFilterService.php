<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;


class ProductFilterService
{
    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query->filter($filters);
    }
 
    public function applySearch(Builder $query, string $searchTerm): Builder
    {
        return $query->filter(['search' => $searchTerm]);
    }

    public function applyStockStatusFilter(Builder $query, string $status): Builder
    {
        return $query->stockStatus($status);
    }

    public function applySorting(Builder $query, ?string $sortBy, string $sortOrder = 'asc'): Builder
    {
        return $query->sortBy($sortBy ?? 'created_at', $sortOrder);
    }
   
    public function getFilterMetadata(Builder $query, array $filters): array
    {
        $baseQuery = clone $query;

        return [
            'total_count' => $baseQuery->count(),
            'filters_applied' => array_filter($filters, fn($value) => !empty($value)),
        ];
    }
}
