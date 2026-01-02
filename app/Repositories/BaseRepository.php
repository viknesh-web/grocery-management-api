<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Base Repository
 * 
 * Abstract base class providing common CRUD operations and query building.
 * All repositories should extend this class and implement the model() method.
 * 
 * @template TModel of Model
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Get the model class name.
     * 
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Get a new query builder instance.
     *
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        $model = $this->model();
        return $model::query();
    }

    /**
     * Find a model by ID.
     *
     * @param int $id
     * @param array $relations Relations to eager load
     * @return TModel|null
     */
    public function find(int $id, array $relations = []): ?Model
    {
        $query = $this->query();
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->find($id);
    }

    /**
     * Find a model by ID or throw an exception.
     *
     * @param int $id
     * @param array $relations Relations to eager load
     * @return TModel
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id, array $relations = []): Model
    {
        $query = $this->query();
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query->findOrFail($id);
    }

    /**
     * Get all models with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection<int, TModel>
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($filters, $relations)->get();
    }

    /**
     * Get all models with caching.
     *
     * @param array $filters
     * @param array $relations
     * @param string|null $cacheKey Custom cache key (if null, auto-generated)
     * @param int $ttl Cache TTL in seconds
     * @return Collection<int, TModel>
     */
    public function allCached(
        array $filters = [],
        array $relations = [],
        ?string $cacheKey = null,
        int $ttl = 1800
    ): Collection {
        $cacheKey = $cacheKey ?? $this->getCacheKey('list', $filters);
        
        return Cache::remember($cacheKey, $ttl, function () use ($filters, $relations) {
            return $this->all($filters, $relations);
        });
    }

    /**
     * Get paginated models with optional filters and relations.
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
     * Create a new model.
     *
     * @param array $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        $model = $this->model();
        return $model::create($data);
    }

    /**
     * Update an existing model.
     *
     * @param Model $model
     * @param array $data
     * @return TModel
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    /**
     * Delete a model.
     *
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Search models by query string.
     * 
     * Assumes the model has a scopeSearch() method.
     *
     * @param string $query
     * @param array $relations
     * @return Collection<int, TModel>
     */
    public function search(string $query, array $relations = []): Collection
    {
        return $this->query()
            ->search($query)
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get active models.
     * 
     * Assumes the model has a scopeActive() method.
     *
     * @param array $relations
     * @return Collection<int, TModel>
     */
    public function getActive(array $relations = []): Collection
    {
        return $this->query()
            ->active()
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get active models with caching.
     *
     * @param array $relations
     * @param string|null $cacheKey
     * @param int $ttl
     * @return Collection<int, TModel>
     */
    public function getActiveCached(
        array $relations = [],
        ?string $cacheKey = null,
        int $ttl = 3600
    ): Collection {
        $cacheKey = $cacheKey ?? $this->getCacheKey('active', []);
        
        return Cache::remember($cacheKey, $ttl, function () use ($relations) {
            return $this->getActive($relations);
        });
    }

    /**
     * Build query with common logic for filtering, sorting, and relations.
     * 
     * This method applies:
     * - Filter scope (if model has scopeFilter())
     * - Relations eager loading
     * - Default sorting
     *
     * @param array $filters
     * @param array $relations
     * @return Builder<TModel>
     */
    protected function buildQuery(array $filters = [], array $relations = []): Builder
    {
        $query = $this->query();

        // Apply filter scope if it exists
        if (method_exists($this->model(), 'scopeFilter')) {
            $query->filter($filters);
        }

        // Eager load relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply default sorting
        $sortBy = $filters['sort_by'] ?? $this->getDefaultSortColumn();
        $sortOrder = $filters['sort_order'] ?? $this->getDefaultSortOrder();
        
        // Use sortBy scope if it exists, otherwise use orderBy
        if (method_exists($this->model(), 'scopeSortBy')) {
            $query->sortBy($sortBy, $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query;
    }

    /**
     * Get the default sort column.
     * 
     * Override in child classes if needed.
     *
     * @return string
     */
    protected function getDefaultSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get the default sort order.
     * 
     * Override in child classes if needed.
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * Generate a cache key for this repository.
     * 
     * Override in child classes for custom cache key generation.
     *
     * @param string $type Cache type (e.g., 'list', 'active', 'single')
     * @param array $filters
     * @return string
     */
    protected function getCacheKey(string $type, array $filters = []): string
    {
        $modelName = strtolower(class_basename($this->model()));
        $filterHash = empty($filters) ? 'all' : md5(json_encode($filters));
        
        return "{$modelName}:{$type}:{$filterHash}";
    }

    /**
     * Clear cache for a specific model.
     * 
     * Override in child classes to implement specific cache clearing logic.
     *
     * @param int|null $id Model ID (null to clear all)
     * @return void
     */
    public function clearCache(?int $id = null): void
    {
        if ($id !== null) {
            $cacheKey = $this->getCacheKey('single', ['id' => $id]);
            Cache::forget($cacheKey);
        }
        
        // Clear list caches (simplified - clear all list caches)
        // Override in child classes for more specific cache clearing
        $this->clearListCache();
    }

    /**
     * Clear all list caches.
     * 
     * Override in child classes for specific cache tag clearing.
     *
     * @return void
     */
    protected function clearListCache(): void
    {
        // Try to use tags if supported (Redis, Memcached)
        if (method_exists(Cache::getStore(), 'tags')) {
            $modelName = strtolower(class_basename($this->model()));
            Cache::tags([$modelName])->flush();
        }
        // For file cache, individual repositories should override this
    }

    /**
     * Count models matching filters.
     *
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Check if a model exists.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }

    /**
     * Get models by IDs.
     *
     * @param array $ids
     * @param array $relations
     * @return Collection<int, TModel>
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
     * Get the first model matching the filters.
     *
     * @param array $filters
     * @param array $relations
     * @return TModel|null
     */
    public function first(array $filters = [], array $relations = []): ?Model
    {
        return $this->buildQuery($filters, $relations)->first();
    }
}

