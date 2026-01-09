<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer Controller
 */
class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Get paginated list of customers.
     *
     * @param IndexCustomerRequest $request
     * @return JsonResponse
     */
    public function index(IndexCustomerRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();            
            $perPage = $pagination['per_page'];
                
            $customers = $this->customerService->getPaginated($filters, $perPage);
            
            return ApiResponse::paginated($customers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve customers', null, 500);
        }
    }

    /**
     * Store a newly created customer.
     * 
     * Note: Phone number normalization is handled in StoreCustomerRequest
     * via PhoneNumberHelper::normalize().
     *
     * @param StoreCustomerRequest $request
     * @return JsonResponse
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $customer = $this->customerService->create(
                $data,
                $request->user()->id
            );
            
            return ApiResponse::success($customer->toArray(), 'Customer created successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create customer', null, 500);
        }
    }

    /**
     * Display the specified customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $customer = $this->customerService->find($request->get('customer')->id);
            
            return ApiResponse::success($customer?->toArray() ?? []);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Customer not found');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve customer', null, 500);
        }
    }

    /**
     * Update the specified customer.
     * 
     * Note: Phone number normalization is handled in UpdateCustomerRequest
     * via PhoneNumberHelper::normalize().
     *
     * @param UpdateCustomerRequest $request
     * @return JsonResponse
     */
    public function update(UpdateCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customerService->update(
                $request->get('customer'),
                $request->validated(),
                $request->user()->id
            );
            
            return ApiResponse::success($customer->toArray(), 'Customer updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Customer not found');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update customer', null, 500);
        }
    }

    /**
     * Remove the specified customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $this->customerService->delete($request->get('customer'));
            
            return ApiResponse::success(null, 'Customer deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Customer not found');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete customer', null, 500);
        }
    }

    /**
     * Toggle the status of the specified customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        try {
            $customer = $this->customerService->toggleStatus(
                $request->get('customer'),
                $request->user()->id
            );
            
            return ApiResponse::success($customer->toArray(), 'Customer status updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Customer not found');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update customer status', null, 500);
        }
    }
}
