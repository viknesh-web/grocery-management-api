<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Traits\HasImageUpload;
use App\Http\Traits\HasStatusToggle;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use HasImageUpload, HasStatusToggle;

    public function __construct(
        private CategoryService $categoryService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'active' => $request->get('active'),
            'root' => $request->boolean('root'),
            'parent_id' => $request->get('parent_id'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'name'),
            'sort_order' => $request->get('sort_order', 'asc'),
        ];

        $perPage = $request->get('per_page', 15);
        $categories = $this->categoryService->getPaginated($filters, $perPage);

        return ApiResponse::paginated($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
        
        return ApiResponse::success($category->toArray(), 'Category created successfully', 201);
    }

    public function show(Request $request)
    {
        $category = $request->get('category');
        $category->load(['creator', 'updater', 'parent']);
        
        return ApiResponse::success($category->toArray());
    }

    public function update(UpdateCategoryRequest $request)
    {
        $category = $request->get('category');
        $data = $request->validated();
        $category = $this->categoryService->update($category, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
        
        return ApiResponse::success($category->toArray(), 'Category updated successfully');
    }

    public function destroy(Request $request)
    {
        $category = $request->get('category');
        $this->categoryService->delete($category);
        
        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    public function toggleStatus(Request $request)
    {
        $category = $request->get('category');
        $category = $this->toggleModelStatus($category, $this->categoryService, $request->user()->id);
        
        return ApiResponse::success($category->toArray(), 'Category status updated successfully');
    }

    public function reorder(Request $request)
    {
        // Display order is no longer supported in the schema
        // This endpoint is kept for API compatibility but does nothing
        return response()->json([
            'success' => true,
            'message' => 'Category reordering is no longer supported',
        ]);
    }

    public function search(Request $request, string $query)
    {
        $categories = $this->categoryService->search($query);

        return ApiResponse::success($categories->map(fn($category) => $category->toArray())->toArray());
    }

    public function tree(Request $request)
    {
        $categories = $this->categoryService->getTree();
        
        return ApiResponse::success($categories->map(fn($category) => $category->toArray())->toArray());
    }

    public function products(Request $request)
    {
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
    }
}
