<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer Repository Interface
 * 
 * Defines the contract for customer data access operations.
 */
interface CustomerRepositoryInterface
{
    /**
     * Get all customers with optional filters and relations.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function all(array $filters = [], array $relations = []): Collection;

    /**
     * Get paginated customers with optional filters and relations.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * Find a customer by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Customer|null
     */
    public function find(int $id, array $relations = []): ?Customer;

    /**
     * Create a new customer.
     *
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer;

    /**
     * Update a customer.
     *
     * @param Customer $customer
     * @param array $data
     * @return bool
     */
    public function update(Customer $customer, array $data): bool;

    /**
     * Delete a customer.
     *
     * @param Customer $customer
     * @return bool
     */
    public function delete(Customer $customer): bool;

    /**
     * Search customers by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection;

    /**
     * Get active customers.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActive(array $relations = []): Collection;
}



