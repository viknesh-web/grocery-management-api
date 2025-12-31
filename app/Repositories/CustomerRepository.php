<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer Repository
 * 
 * Handles all database operations for customers.
 */
class CustomerRepository
{
    /**
     * Get all customers with optional filters and relations.
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
     * Get paginated customers with optional filters and relations.
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
        return Customer::query()
            ->filter($filters) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');
    }


    /**
     * Search customers by query string.
     *
     * @param string $query
     * @param array $relations
     * @return Collection
     */
    public function search(string $query, array $relations = []): Collection
    {
        return Customer::search($query) // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }

    /**
     * Get active customers.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActive(array $relations = []): Collection
    {
        return Customer::active() // Use scope!
            ->when(!empty($relations), fn($q) => $q->with($relations))
            ->get();
    }
}



