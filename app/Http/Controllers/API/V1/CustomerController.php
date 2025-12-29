<?php

namespace App\Http\Controllers\API\V1;

use App\Helper\DataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CustomerCollection;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Validator\CustomerValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), CustomerValidator::onCreate(), CustomerValidator::messages());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCustomer($data);

        try {
            $customer = $this->customerService->create($data,
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

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), CustomerValidator::onUpdate($customer->id), CustomerValidator::messages());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCustomer($data, $customer->id);

        try {
            $customer = $this->customerService->update(
                $customer,
                $data,
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

    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $this->customerService->delete($customer);

            return response()->json(['message' => 'Customer deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleStatus(Request $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->toggleStatus($customer, $request->user()->id);

        return response()->json([
            'message' => 'Customer status updated successfully',
            'data' => new CustomerResource($customer),
        ], 200);
    }

}
