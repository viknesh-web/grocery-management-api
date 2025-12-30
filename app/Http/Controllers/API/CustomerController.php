<?php

namespace App\Http\Controllers\API;

use App\Helper\DataNormalizer;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Traits\HasStatusToggle;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Validator\CustomerValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), CustomerValidator::onCreate(), CustomerValidator::messages());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCustomer($data);

        try {
            $customer = $this->customerService->create($data, $request->user()->id);

            return ApiResponse::success($customer, 'Customer created successfully', 201);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to create customer");
        }
    }

    public function show(Customer $customer)
    {
        $customer = $this->customerService->find($customer->id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }

        return ApiResponse::success($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), CustomerValidator::onUpdate($customer->id), CustomerValidator::messages());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCustomer($data, $customer->id);

        try {
            $customer = $this->customerService->update($customer, $data, $request->user()->id);

            return ApiResponse::success($customer, 'Customer updated successfully');
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to update customer");
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            $this->customerService->delete($customer);

            return ApiResponse::success(null, 'Customer deleted successfully');
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to delete customer");
        }
    }

    public function toggleStatus(Request $request, Customer $customer)
    {
        $customer = $this->toggleModelStatus($customer, $this->customerService, $request->user()->id);

        return ApiResponse::success($customer, 'Customer status updated successfully');
    }

}
