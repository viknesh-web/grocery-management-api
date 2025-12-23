<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product Repository
 * 
 * Handles all database operations for products.
 */
class ProductRepository implements ProductRepositoryInterface
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
        $query = Product::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $query = $this->applyFilters($query, $filters);
        $query = $this->applySorting($query, $filters);

        return $query->get();
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
        $query = Product::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $query = $this->applyFilters($query, $filters);
        $query = $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Find a product by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Product|null
     */
    public function find(int $id, array $relations = []): ?Product
    {
        $query = Product::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Create a new product.
     *
     * @param array $data
     * @return Product
     */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * Update a product.
     *
     * @param Product $product
     * @param array $data
     * @return bool
     */
    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    /**
     * Delete a product.
     *
     * @param Product $product
     * @return bool
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
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

        $query = $this->applyFilters($query, $filters);
        $query = $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Apply filters to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyFilters($query, array $filters)
    {
        return $this->filterService->applyFilters($query, $filters);
    }

    /**
     * Apply sorting to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applySorting($query, array $filters)
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        return $this->filterService->applySorting($query, $sortBy, $sortOrder);
    }
}



