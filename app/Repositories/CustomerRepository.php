<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer Repository
 * 
 * Handles all database operations for customers.
 */
class CustomerRepository implements CustomerRepositoryInterface
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
        $query = Customer::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('whatsapp_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('address', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('area', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->get();
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
        $query = Customer::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (isset($filters['active'])) {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('whatsapp_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('address', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('area', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find a customer by ID with optional relations.
     *
     * @param int $id
     * @param array $relations
     * @return Customer|null
     */
    public function find(int $id, array $relations = []): ?Customer
    {
        $query = Customer::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Create a new customer.
     *
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Update a customer.
     *
     * @param Customer $customer
     * @param array $data
     * @return bool
     */
    public function update(Customer $customer, array $data): bool
    {
        return $customer->update($data);
    }

    /**
     * Delete a customer.
     *
     * @param Customer $customer
     * @return bool
     */
    public function delete(Customer $customer): bool
    {
        return $customer->delete();
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
              ->orWhere('area', 'like', '%' . $query . '%');
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
        $query = Customer::where('active', true);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }
}



