<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryCollection;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Product\ProductCollection;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Category Controller
 * 
 * Handles HTTP requests for category management operations.
 * Business logic is delegated to CategoryService.
 */
class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): CategoryCollection
    {
        $filters = [
            'active' => $request->get('active'),
            'root' => $request->boolean('root'),
            'parent_id' => $request->get('parent_id'),
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'display_order'),
            'sort_order' => $request->get('sort_order', 'asc'),
        ];

        $perPage = $request->get('per_page', 15);
        $categories = $this->categoryService->getPaginated($filters, $perPage);

        return new CategoryCollection($categories);
    }

    /**
     * Store a newly created category.
     * Note: Route uses 'create' but method is 'store' for Laravel convention.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $request->validated(),
            $request->file('image'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $category = $this->categoryService->find($category->id);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'data' => new CategoryResource($category),
        ], 200);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $category = $this->categoryService->update(
                $category,
                $request->validated(),
                $request->file('image'),
                $request->boolean('image_removed', false),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category);

            return response()->json([
                'message' => 'Category deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle category active status.
     */
    public function toggleStatus(Request $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->toggleStatus($category, $request->user()->id);

        return response()->json([
            'message' => 'Category status updated successfully',
            'data' => new CategoryResource($category),
        ], 200);
    }

    /**
     * Reorder categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.display_order' => ['required', 'integer', 'min:0'],
        ]);

        $this->categoryService->reorder($request->categories);

        return response()->json([
            'message' => 'Categories reordered successfully',
        ], 200);
    }

    /**
     * Search categories.
     */
    public function search(Request $request, string $query): CategoryCollection
    {
        $categories = $this->categoryService->search($query);

        return new CategoryCollection($categories);
    }

    /**
     * Get category tree (hierarchical structure).
     */
    public function tree(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getTree();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ], 200);
    }

    /**
     * Get products in a category.
     */
    public function products(Request $request, Category $category): ProductCollection
    {
        $filters = [
            'search' => $request->get('search'),
            'enabled' => $request->get('enabled'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $products = $this->categoryService->getProducts($category, $filters, $perPage);

        return new ProductCollection($products);
    }
}
