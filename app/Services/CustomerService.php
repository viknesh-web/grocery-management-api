<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer Service
 * 
 * Handles all business logic for customer operations.
 */
class CustomerService
{
    public function __construct(
        private CustomerRepositoryInterface $repository
    ) {}

    /**
     * Get paginated customers with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        return $this->repository->paginate($filters, $perPage, $relations);
    }

    /**
     * Get a customer by ID.
     *
     * @param int $id
     * @return Customer|null
     */
    public function find(int $id): ?Customer
    {
        $relations = [];
        return $this->repository->find($id, $relations);
    }

    /**
     * Create a new customer.
     *
     * @param array $data
     * @param int $userId
     * @return Customer
     */
    public function create(array $data, int $userId): Customer
    {
        DB::beginTransaction();
        try {
            $customer = $this->repository->create($data);

            DB::commit();
            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a customer.
     *
     * @param Customer $customer
     * @param array $data
     * @param int $userId
     * @return Customer
     */
    public function update(Customer $customer, array $data, int $userId): Customer
    {
        DB::beginTransaction();
        try {
            $this->repository->update($customer, $data);
            $customer->refresh();

            DB::commit();
            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a customer.
     *
     * @param Customer $customer
     * @return bool
     */
    public function delete(Customer $customer): bool
    {
        DB::beginTransaction();
        try {
            $result = $this->repository->delete($customer);

            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle customer active status.
     *
     * @param Customer $customer
     * @param int $userId
     * @return Customer
     */
    public function toggleStatus(Customer $customer, int $userId): Customer
    {
        $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
        $this->repository->update($customer, [
            'status' => $newStatus,
        ]);

        return $customer->fresh();
    }
}



