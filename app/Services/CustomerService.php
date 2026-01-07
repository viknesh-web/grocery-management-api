<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer Service
 * 
 */
class CustomerService extends BaseService
{
    public function __construct(
        private CustomerRepository $repository
    ) {}
  
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        
        $customers = $this->repository->paginate($filters, $perPage, $relations);

        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));

        $customers->filters_applied = $filtersApplied;
        $customers->total_filtered = $totalFiltered;

        return $customers;
    }

    public function find(int $id): ?Customer
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find customer with ID: {$id}");
    }

    public function findOrFail(int $id): Customer
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find customer with ID: {$id}");
    }

    public function create(array $data, int $userId): Customer
    {
        return $this->transaction(function () use ($data, $userId) {
      
            $data = $this->prepareCustomerData($data, $userId);
            $customer = $this->repository->create($data);

            return $customer;
        }, 'Failed to create customer');
    }

    public function update(Customer $customer, array $data, int $userId): Customer
    {
        return $this->transaction(function () use ($customer, $data, $userId) {
         
            $oldValues = $this->getOldCustomerValues($customer);

            $data = $this->prepareCustomerData($data, $userId, $customer);

            $customer = $this->repository->update($customer, $data);

            return $customer;
        }, 'Failed to update customer');
    }

    public function delete(Customer $customer): bool
    {
        return $this->transaction(function () use ($customer) {          
            $result = $this->repository->delete($customer);       

            return $result;
        }, 'Failed to delete customer');
    }
  
    public function toggleStatus(Customer $customer, int $userId): Customer
    {
        return $this->transaction(function () use ($customer, $userId) {         
            $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
            
            $customer = $this->repository->update($customer, [
                'status' => $newStatus,
            ]);

            return $customer;
        }, 'Failed to toggle customer status');
    }

    public function search(string $query): Collection
    {
        $relations = [];
        return $this->repository->search($query, $relations);
    }

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


   
}
