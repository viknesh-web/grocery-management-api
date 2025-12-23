<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Category Repository
 * 
 * Handles all database operations for categories.
 */
class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Get all categories with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection
    {
        $query = Category::query();

        // Apply relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['root']) && $filters['root']) {
            $query->root();
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'display_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder)->orderBy('name');

        return $query->get();
    }

    /**
     * Get paginated categories with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        $query = Category::query();

        // Apply relations
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['root']) && $filters['root']) {
            $query->root();
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'display_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder)->orderBy('name');

        $categories = $query->paginate($perPage);

        // Add products count
        $categories->getCollection()->transform(function ($category) {
            $category->products_count = $category->products()->count();
            return $category;
        });

        return $categories;
    }

    /**
     * Find a category by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Category|null
     */
    public function find(int $id, array $relations = []): ?Category
    {
        $query = Category::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function update(Category $category, array $data): bool
    {
        return $category->update($data);
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function delete(Category $category): bool
    {
        return $category->delete();
    }

    /**
     * Search categories by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection
    {
        $builder = Category::query();
        $builder->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%");

        if (!empty($relations)) {
            $builder->with($relations);
        }

        $categories = $builder->ordered()->get();

        $categories->transform(function ($category) {
            $category->products_count = $category->products()->count();
            return $category;
        });

        return $categories;
    }

    /**
     * Get root categories (categories without parent).
     *
     * @param array $relations
     * @return Collection
     */
    public function getRootCategories(array $relations = []): Collection
    {
        $query = Category::root()->active()->ordered();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Get active categories.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActiveCategories(array $relations = []): Collection
    {
        $query = Category::active();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * Reorder categories by display_order.
     *
     * @param array $items Array of ['id' => int, 'display_order' => int]
     * @return void
     */
    public function reorder(array $items): void
    {
        foreach ($items as $item) {
            Category::where('id', $item['id'])->update([
                'display_order' => $item['display_order'],
            ]);
        }
    }

    /**
     * Check if category has products.
     *
     * @param Category $category
     * @return bool
     */
    public function hasProducts(Category $category): bool
    {
        return $category->products()->count() > 0;
    }
}

