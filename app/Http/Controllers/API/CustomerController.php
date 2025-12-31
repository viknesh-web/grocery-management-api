<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Traits\HasStatusToggle;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use HasStatusToggle;

    public function __construct(
        private CustomerService $customerService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $customers = $this->customerService->getPaginated($filters, $perPage);

        return ApiResponse::paginated($customers);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $customer = $this->customerService->create($data, $request->user()->id);

        return ApiResponse::success($customer->toArray(), 'Customer created successfully', 201);
    }

    public function show(Customer $customer)
    {
        $customer = $this->customerService->find($customer->id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }

        return ApiResponse::success($customer->toArray());
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $customer = $this->customerService->update($customer, $data, $request->user()->id);

        return ApiResponse::success($customer->toArray(), 'Customer updated successfully');
    }

    public function destroy(Customer $customer)
    {
        $this->customerService->delete($customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    public function toggleStatus(Request $request, Customer $customer)
    {
        $customer = $this->toggleModelStatus($customer, $this->customerService, $request->user()->id);

        return ApiResponse::success($customer->toArray(), 'Customer status updated successfully');
    }

}
