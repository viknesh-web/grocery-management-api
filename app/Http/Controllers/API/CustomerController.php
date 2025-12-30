<?php

namespace App\Http\Controllers\API;

use App\Helper\DataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CustomerCollection;
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
            'status' => $request->get('status'),
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
                'data' => $customer,
            ], 201);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to create customer");
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
            'data' => $customer,
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
                'data' => $customer,
            ], 200);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to update customer");
        }
    }

    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $this->customerService->delete($customer);

            return response()->json(['message' => 'Customer deleted successfully'], 200);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to delete customer");
        }
    }

    public function toggleStatus(Request $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->toggleStatus($customer, $request->user()->id);

        return response()->json([
            'message' => 'Customer status updated successfully',
            'data' => $customer,
        ], 200);
    }

}
