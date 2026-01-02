<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product Repository
 * 
 * Handles all database operations for products.
 * Follows Repository pattern: data access only, no business logic.
 * 
 * Responsibilities:
 * - Query building using model scopes
 * - CRUD operations
 * - Filtering and pagination
 * - Cache key generation (caching delegated to service layer)
 */
class ProductRepository extends BaseRepository
{
    /**
     * Get the model class name.
     *
     * @return string
     */
    protected function model(): string
    {
        return Product::class;
    }

    /**
     * Get default sort column for products.
     *
     * @return string
     */
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get default sort order for products.
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * Uses Product model scopes: filter(), sortBy()
     *
     * @param array $filters
     * @param array $relations
     * @return Builder
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        // Apply filter scope (handles: search, category_id, status, product_type, has_discount, stock_status)
        $query->filter($filters);

        // Eager load relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply sorting using model scope
        $sortBy = $filters['sort_by'] ?? $this->getDefaultSortColumn();
        $sortOrder = $filters['sort_order'] ?? $this->getDefaultSortOrder();
        $query->sortBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Get all products with optional filters and relations.
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection<int, Product>
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
     * Search products by query string.
     * 
     * Uses Product model scopeSearch().
     *
     * @param string $query
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function search(string $query, array $relations = []): Collection
    {
        return $this->query()
            ->search($query)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get active products.
     * 
     * Uses Product model scopeActive().
     * 
     * Note: Caching should be handled by the service layer.
     *
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getActive(array $relations = []): Collection
    {
        return $this->query()
            ->active()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get inactive products.
     * 
     * Uses Product model scopeInactive().
     *
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getInactive(array $relations = []): Collection
    {
        return $this->query()
            ->inactive()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get products by category ID.
     * 
     * Uses Product model scopeFilter() with category_id.
     *
     * @param int $categoryId
     * @param array $filters Additional filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getByCategory(int $categoryId, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        // Add category_id to filters to use the filter scope
        $filters['category_id'] = $categoryId;

        return $this->paginate($filters, $perPage, $relations);
    }

    /**
     * Get products by product type.
     * 
     * Uses Product model scopes: scopeDaily() or scopeStandard().
     *
     * @param string $type 'daily' or 'standard'
     * @param array $filters Additional filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getByType(string $type, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $filters['product_type'] = $type;
        return $this->paginate($filters, $perPage, $relations);
    }

    /**
     * Get products in stock.
     * 
     * Uses Product model scopeInStock().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getInStock(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->inStock()->get();
    }

    /**
     * Get products with active discount.
     * 
     * Uses Product model scopeWithActiveDiscount().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getWithActiveDiscount(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->withActiveDiscount()->get();
    }

    /**
     * Get products without active discount.
     * 
     * Uses Product model scopeWithoutActiveDiscount().
     *
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getWithoutActiveDiscount(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->withoutActiveDiscount()->get();
    }

    /**
     * Get products by stock status.
     * 
     * Uses Product model scopeStockStatus().
     *
     * @param string $status 'in_stock', 'low_stock', or 'out_of_stock'
     * @param array $filters Additional filters
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function getByStockStatus(string $status, array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->stockStatus($status)->get();
    }

    /**
     * Count products matching filters.
     *
     * @param array $filters
     * @return int
     */
    public function countByFilters(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Check if a product exists by ID.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }

    /**
     * Get products by IDs.
     *
     * @param array $ids
     * @param array $relations
     * @return Collection<int, Product>
     */
    public function findMany(array $ids, array $relations = []): Collection
    {
        $query = $this->query()->whereIn('id', $ids);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Get cache key for product list.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param array $filters
     * @return string
     */
    public function getListCacheKey(array $filters = []): string
    {
        return CacheService::productListKey($filters);
    }

    /**
     * Get cache key for a single product.
     * 
     * Delegates to CacheService for consistency.
     * Actual caching should be handled by the service layer.
     *
     * @param int $id
     * @return string
     */
    public function getSingleCacheKey(int $id): string
    {
        return CacheService::productKey($id);
    }

    /**
     * Find a product by ID with lock for update.
     * 
     * Used for preventing race conditions during concurrent updates.
     * Locks the row until the transaction completes.
     *
     * @param int $id
     * @param array $relations
     * @return Product|null
     */
    public function findWithLock(int $id, array $relations = []): ?Product
    {
        $query = $this->query()
            ->where('id', $id)
            ->lockForUpdate();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * Find a product by ID with lock for update or throw exception.
     * 
     * Used for preventing race conditions during concurrent updates.
     * Locks the row until the transaction completes.
     *
     * @param int $id
     * @param array $relations
     * @return Product
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFailWithLock(int $id, array $relations = []): Product
    {
        $query = $this->query()
            ->where('id', $id)
            ->lockForUpdate();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->firstOrFail();
    }
}
