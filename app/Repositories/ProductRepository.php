<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;


class ProductRepository extends BaseRepository
{
    
    protected function model(): string
    {
        return Product::class;
    }
  
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }
   
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();
        $query->filter($filters);
        if (!empty($relations)) {
            $query->with($relations);
        }

        $sortBy = $filters['sort_by'] ?? $this->getDefaultSortColumn();
        $sortOrder = $filters['sort_order'] ?? $this->getDefaultSortOrder();
        $query->sortBy($sortBy, $sortOrder);

        return $query;
    }

    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }
  
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildQuery($filters, $relations)->paginate($perPage);
    }
   
    public function search(string $query, array $relations = []): Collection
    {
        return $this->query()
            ->search($query)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    public function getActive(array $relations = []): Collection
    {
        return $this->query()
            ->active()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }
   
    public function getInactive(array $relations = []): Collection
    {
        return $this->query()
            ->inactive()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    public function getByCategory(int $categoryId, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $filters['category_id'] = $categoryId;

        return $this->paginate($filters, $perPage, $relations);
    }
 
    public function getByType(string $type, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $filters['product_type'] = $type;
        return $this->paginate($filters, $perPage, $relations);
    }
 
    public function getInStock(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->inStock()->get();
    }

    public function getWithActiveDiscount(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->withActiveDiscount()->get();
    }

    public function getWithoutActiveDiscount(array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->withoutActiveDiscount()->get();
    }
   
    public function getByStockStatus(string $status, array $filters = [], array $relations = []): Collection
    {
        $query = $this->buildQuery($filters, $relations);
        return $query->stockStatus($status)->get();
    }
  
    public function countByFilters(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }
  
    public function exists(int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }
  
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
