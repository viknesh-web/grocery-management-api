<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Product Controller
 * 
 */
class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(IndexProductRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $perPage = $request->get('per_page', 15);
            
            $products = $this->productService->getPaginated($filters, $perPage);
            
            return ApiResponse::paginated($products);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve products', null, 500);
        }
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $product = $this->productService->create(
                $data,
                $request->file('image'),
                $request->user()->id
            );
            
            return ApiResponse::success($product->toArray(), 'Product created successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create product', null, 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $product = $this->productService->find($request->get('product')->id);
            
            return ApiResponse::success($product?->toArray() ?? []);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve product', null, 500);
        }
    }

    public function update(UpdateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->update(
                $request->get('product'),
                $request->validated(),
                $request->file('image'),
                $request->boolean('image_removed', false),
                $request->user()->id
            );
            
            return ApiResponse::success($product->toArray(), 'Product updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update product', null, 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $this->productService->delete($request->get('product'));
            
            return ApiResponse::success(null, 'Product deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete product', null, 500);
        }
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        try {
            $product = $this->productService->toggleStatus(
                $request->get('product'),
                $request->user()->id
            );
            
            return ApiResponse::success($product->toArray(), 'Product status updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update product status', null, 500);
        }
    }
}
