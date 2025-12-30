<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Category Repository Interface
 * 
 * Defines the contract for category data access operations.
 */
interface CategoryRepositoryInterface
{
    /**
     * Get all categories with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection;

    /**
     * Get paginated categories with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * Find a category by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Category|null
     */
    public function find(int $id, array $relations = []): ?Category;

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function create(array $data): Category;

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function update(Category $category, array $data): bool;

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function delete(Category $category): bool;

    /**
     * Search categories by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection;

    /**
     * Get root categories (categories without parent).
     *
     * @param array $relations
     * @return Collection
     */
    public function getRootCategories(array $relations = []): Collection;

    /**
     * Get active categories.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActiveCategories(array $relations = []): Collection;

    /**
     * Reorder categories by display_order.
     *
     * @param array $items Array of ['id' => int, 'display_order' => int]
     * @return void
     */
    public function reorder(array $items): void;

    /**
     * Check if category has products.
     *
     * @param Category $category
     * @return bool
     */
    public function hasProducts(Category $category): bool;
}




