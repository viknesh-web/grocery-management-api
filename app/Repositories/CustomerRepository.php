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
        $query = Customer::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (isset($filters['status']) && in_array($filters['status'], ['active', 'inactive'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('whatsapp_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('address', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('landmark', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query;
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
        $builder = Customer::query();

        if (!empty($relations)) {
            $builder->with($relations);
        }

        return $builder->where(function ($q) use ($query) {
            $q->where('name', 'like', '%' . $query . '%')
              ->orWhere('whatsapp_number', 'like', '%' . $query . '%')
              ->orWhere('address', 'like', '%' . $query . '%')
              ->orWhere('landmark', 'like', '%' . $query . '%');
        })->get();
    }

    /**
     * Get active customers.
     *
     * @param array $relations
     * @return Collection
     */
    public function getActive(array $relations = []): Collection
    {
        $query = Customer::where('status', 'active');

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }
}



