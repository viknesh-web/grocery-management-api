<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\ProductFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product Repository
 * 
 * Handles all database operations for products.
 */
class ProductRepository
{
    public function __construct(
        private ProductFilterService $filterService
    ) {}

    /**
     * Get all products with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }

    /**
     * Get paginated products with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters, $relations)->paginate($perPage);
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildQuery(array $filters = [], array $relations = [])
    {
        $query = Product::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $query = $this->filterService->applyFilters($query, $filters);

        return $this->filterService->applySorting(
            $query,
            $filters['sort_by'] ?? 'created_at',
            $filters['sort_order'] ?? 'desc'
        );
    }


    /**
     * Search products by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection
    {
        $builder = Product::query();

        if (!empty($relations)) {
            $builder->with($relations);
        }

        return $builder->search($query)->get();
    }

    /**
     * Get enabled products.
     *
     * @param array $relations
     * @return Collection
     */
    public function getEnabled(array $relations = []): Collection
    {
        $query = Product::enabled();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Get products by category ID.
     *
     * @param int $categoryId
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getByCategory(int $categoryId, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $query = Product::where('category_id', $categoryId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        $query = $this->filterService->applyFilters($query, $filters);

        return $this->filterService->applySorting(
            $query,
            $filters['sort_by'] ?? 'created_at',
            $filters['sort_order'] ?? 'desc'
        )->paginate($perPage);
    }

}



