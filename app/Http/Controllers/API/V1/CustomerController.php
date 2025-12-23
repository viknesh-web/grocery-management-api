<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\Customer\CustomerCollection;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer Controller
 * 
 * Handles HTTP requests for customer management operations.
 * Business logic is delegated to CustomerService.
 */
class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): CustomerCollection
    {
        $filters = [
            'search' => $request->get('search'),
            'active' => $request->get('active'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $customers = $this->customerService->getPaginated($filters, $perPage);

        return new CustomerCollection($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->create(
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Customer created successfully',
                'data' => new CustomerResource($customer),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer = $this->customerService->find($customer->id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found',
            ], 404);
        }

        return response()->json([
            'data' => new CustomerResource($customer),
        ], 200);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            $customer = $this->customerService->update(
                $customer,
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Customer updated successfully',
                'data' => new CustomerResource($customer),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $this->customerService->delete($customer);

            return response()->json([
                'message' => 'Customer deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle customer active status.
     */
    public function toggleStatus(Request $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->toggleStatus($customer, $request->user()->id);

        return response()->json([
            'message' => 'Customer status updated successfully',
            'data' => new CustomerResource($customer),
        ], 200);
    }
}
