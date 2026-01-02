<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer Service
 * 
 * Handles all business logic for customer operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Transaction management
 * - Cache management (delegated to CacheService)
 * - Status toggle logic
 * - Search functionality
 */
class CustomerService extends BaseService
{
    public function __construct(
        private CustomerRepository $repository
    ) {}

    /**
     * Get paginated customers with filters.
     * 
     * Handles:
     * - Customer pagination (via repository)
     * - Filter metadata calculation (via repository)
     * - Relation eager loading
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        
        // Get paginated customers via repository
        $customers = $this->repository->paginate($filters, $perPage, $relations);

        // Get filter metadata using repository's count method
        // This ensures we use the same filter logic as the pagination
        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));

        // Store metadata as dynamic properties for controller to access
        $customers->filters_applied = $filtersApplied;
        $customers->total_filtered = $totalFiltered;

        return $customers;
    }

    /**
     * Get a customer by ID.
     * 
     * Handles:
     * - Customer retrieval (via repository)
     * - Relation eager loading
     * - Error handling (returns null if not found)
     *
     * @param int $id
     * @return Customer|null
     */
    public function find(int $id): ?Customer
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find customer with ID: {$id}");
    }

    /**
     * Get a customer by ID or throw an exception.
     * 
     * Handles:
     * - Customer retrieval (via repository)
     * - Relation eager loading
     * - Throws exception if not found
     *
     * @param int $id
     * @return Customer
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): Customer
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find customer with ID: {$id}");
    }

    /**
     * Create a new customer.
     * 
     * Handles:
     * - Data preparation (user tracking)
     * - Customer creation (via repository)
     * - Cache clearing
     *
     * @param array $data
     * @param int $userId
     * @return Customer
     * @throws \Exception
     */
    public function create(array $data, int $userId): Customer
    {
        return $this->transaction(function () use ($data, $userId) {
            // Prepare data (user tracking)
            $data = $this->prepareCustomerData($data, $userId);

            // Create customer via repository
            $customer = $this->repository->create($data);

            // Post-creation actions (cache clearing)
            $this->afterCreate($customer, $data);

            return $customer;
        }, 'Failed to create customer');
    }

    /**
     * Update a customer.
     * 
     * Handles:
     * - Data preparation (user tracking)
     * - Customer update (via repository)
     * - Cache clearing
     *
     * @param Customer $customer
     * @param array $data
     * @param int $userId
     * @return Customer
     * @throws \Exception
     */
    public function update(Customer $customer, array $data, int $userId): Customer
    {
        return $this->transaction(function () use ($customer, $data, $userId) {
            // Store old values for comparison
            $oldValues = $this->getOldCustomerValues($customer);

            // Prepare data (user tracking)
            $data = $this->prepareCustomerData($data, $userId, $customer);

            // Update customer via repository
            $customer = $this->repository->update($customer, $data);

            // Post-update actions (cache clearing)
            $this->afterUpdate($customer, $data, $oldValues);

            return $customer;
        }, 'Failed to update customer');
    }

    /**
     * Delete a customer.
     * 
     * Handles:
     * - Customer deletion (via repository)
     * - Cache clearing
     *
     * @param Customer $customer
     * @return bool
     * @throws \Exception
     */
    public function delete(Customer $customer): bool
    {
        return $this->transaction(function () use ($customer) {
            // Delete customer via repository
            $result = $this->repository->delete($customer);

            // Post-deletion actions (cache clearing)
            $this->afterDelete($customer);

            return $result;
        }, 'Failed to delete customer');
    }

    /**
     * Toggle customer active status.
     * 
     * Handles:
     * - Status calculation (business logic)
     * - Customer update (via repository)
     * - User tracking
     * - Cache clearing
     *
     * @param Customer $customer
     * @param int $userId
     * @return Customer
     * @throws \Exception
     */
    public function toggleStatus(Customer $customer, int $userId): Customer
    {
        return $this->transaction(function () use ($customer, $userId) {
            // Calculate new status (business logic)
            $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
            
            // Update customer via repository
            $customer = $this->repository->update($customer, [
                'status' => $newStatus,
            ]);

            // Clear cache after status change
            $this->clearModelCache($customer);

            return $customer;
        }, 'Failed to toggle customer status');
    }

    /**
     * Search customers.
     * 
     * Handles:
     * - Customer search (via repository)
     * - Relation eager loading
     *
     * @param string $query
     * @return Collection<int, Customer>
     */
    public function search(string $query): Collection
    {
        $relations = [];
        return $this->repository->search($query, $relations);
    }

    /**
     * Prepare customer data for create/update.
     * 
     * Handles:
     * - User tracking (created_by, updated_by)
     *
     * @param array $data
     * @param int $userId
     * @param Customer|null $customer Existing customer (for updates)
     * @return array
     */
    protected function prepareCustomerData(array $data, int $userId, ?Customer $customer = null): array
    {
        // Add user tracking
        if ($customer === null) {
            // Creating new customer
            // Note: Customers may not have created_by/updated_by fields
        } else {
            // Updating existing customer
            // Note: Customers may not have updated_by field
        }

        return $data;
    }

    /**
     * Get old customer values for comparison.
     *
     * @param Customer $customer
     * @return array
     */
    protected function getOldCustomerValues(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'whatsapp_number' => $customer->whatsapp_number,
            'address' => $customer->address,
            'landmark' => $customer->landmark,
            'remarks' => $customer->remarks,
            'status' => $customer->status,
        ];
    }

    /**
     * Clear cache for a customer.
     * 
     * Override from BaseService to implement customer-specific cache clearing.
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param int|null $id
     * @return void
     */
    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        if ($model instanceof Customer) {
            CacheService::clearCustomer($model->id);
        } elseif ($id !== null) {
            CacheService::clearCustomer($id);
        }
    }

    /**
     * Clear all customer caches.
     * 
     * Override from BaseService to implement customer-specific cache clearing.
     *
     * @return void
     */
    protected function clearAllModelCache(): void
    {
        CacheService::clearCustomerCache();
    }

    /**
     * Perform actions after customer creation.
     * 
     * Override from BaseService to handle post-creation logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @return void
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Clear all customer caches after creation
        $this->clearAllModelCache();
    }

    /**
     * Perform actions after customer update.
     * 
     * Override from BaseService to handle post-update logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @param array $oldData
     * @return void
     */
    protected function afterUpdate(Model $model, array $data, array $oldData): void
    {
        // Clear specific customer cache after update
        if ($model instanceof Customer) {
            $this->clearModelCache($model);
        }
    }

    /**
     * Perform actions after customer deletion.
     * 
     * Override from BaseService to handle post-deletion logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function afterDelete(Model $model): void
    {
        // Clear customer cache after deletion
        if ($model instanceof Customer) {
            $this->clearModelCache($model);
        }
    }
}
