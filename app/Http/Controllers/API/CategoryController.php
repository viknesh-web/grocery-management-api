<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\IndexCategoryRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Category Controller
 */
class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    /**
     * Get paginated list of categories.
     *
     * @param IndexCategoryRequest $request
     * @return JsonResponse
     */
    public function index(IndexCategoryRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $pagination = $request->getPagination();            
            $perPage = $pagination['per_page'];
                
            $categories = $this->categoryService->getPaginated($filters, $perPage);
            
            return ApiResponse::paginated($categories);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve categories', null, 500);
        }
    }

    /**
     * Store a newly created category.
     *
     * @param StoreCategoryRequest $request
     * @return JsonResponse
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $category = $this->categoryService->create(
                $data,
                $request->file('image'),
                $request->user()->id
            );
            
            return ApiResponse::success($category->toArray(), 'Category created successfully', 201);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create category', null, 500);
        }
    }

    /**
     * Display the specified category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $category = $this->categoryService->find($request->get('category')->id);
            
            return ApiResponse::success($category?->toArray() ?? []);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Category not found');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve category', null, 500);
        }
    }

    /**
     * Update the specified category.
     *
     * @param UpdateCategoryRequest $request
     * @return JsonResponse
     */
    public function update(UpdateCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->update(
                $request->get('category'),
                $request->validated(),
                $request->file('image'),
                $request->boolean('image_removed', false),
                $request->user()->id
            );
            
            return ApiResponse::success($category->toArray(), 'Category updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Category not found');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update category', null, 500);
        }
    }

    /**
     * Remove the specified category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $this->categoryService->delete($request->get('category'));
            
            return ApiResponse::success(null, 'Category deleted successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Category not found');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete category', null, 500);
        }
    }

    /**
     * Toggle the status of the specified category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        try {
            $category = $this->categoryService->toggleStatus(
                $request->get('category'),
                $request->user()->id
            );
            
            return ApiResponse::success($category->toArray(), 'Category status updated successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Category not found');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update category status', null, 500);
        }
    } 

    /**
     * Search categories by query string.
     *
     * @param Request $request
     * @param string $query
     * @return JsonResponse
     */
    public function search(Request $request, string $query): JsonResponse
    {
        try {
            
            $categories = $this->categoryService->search($query);
            
            return ApiResponse::success(
                $categories->map(fn($category) => $category->toArray())->toArray()
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to search categories', null, 500);
        }
    }   

    /**
     * Get products in a category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function products(Request $request): JsonResponse
    {
        try {
            $category = $request->get('category');
            $filters = [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            $perPage = $request->get('per_page', 15);
            $products = $this->categoryService->getProducts($category, $filters, $perPage);

            return ApiResponse::paginated($products);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Category not found');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve category products', null, 500);
        }
    }
}
