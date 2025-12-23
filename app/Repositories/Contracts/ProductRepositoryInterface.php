<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product Repository Interface
 * 
 * Defines the contract for product data access operations.
 */
interface ProductRepositoryInterface
{
    /**
     * Get all products with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection;

    /**
     * Get paginated products with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * Find a product by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Product|null
     */
    public function find(int $id, array $relations = []): ?Product;

    /**
     * Create a new product.
     *
     * @param array $data
     * @return Product
     */
    public function create(array $data): Product;

    /**
     * Update a product.
     *
     * @param Product $product
     * @param array $data
     * @return bool
     */
    public function update(Product $product, array $data): bool;

    /**
     * Delete a product.
     *
     * @param Product $product
     * @return bool
     */
    public function delete(Product $product): bool;

    /**
     * Search products by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection;

    /**
     * Get enabled products.
     *
     * @param array $relations
     * @return Collection
     */
    public function getEnabled(array $relations = []): Collection;

    /**
     * Get products by category ID.
     *
     * @param int $categoryId
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getByCategory(int $categoryId, array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator;
}



