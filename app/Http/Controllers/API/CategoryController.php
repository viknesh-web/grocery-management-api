<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
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

        return (new CategoryCollection($categories))
            ->additional([
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'from' => $categories->firstItem(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'to' => $categories->lastItem(),
                    'total' => $categories->total(),
                ],
                'links' => [
                    'first' => $categories->url(1),
                    'last' => $categories->url($categories->lastPage()),
                    'prev' => $categories->previousPageUrl(),
                    'next' => $categories->nextPageUrl(),
                ],
            ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
        
        return (new CategoryResource($category))
            ->additional(['message' => 'Category created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request)
    {
        $category = $request->get('category');
        $category->load(['creator', 'updater', 'parent']);
        
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request)
    {
        $category = $request->get('category');
        $data = $request->validated();
        $category = $this->categoryService->update($category, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
        
        return (new CategoryResource($category))
            ->additional(['message' => 'Category updated successfully']);
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
        
        return (new CategoryResource($category))
            ->additional(['message' => 'Category status updated successfully']);
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

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }

    public function tree(Request $request)
    {
        $categories = $this->categoryService->getTree();
        
        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
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

        return (new ProductCollection($products))
            ->additional([
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'from' => $products->firstItem(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ],
                'links' => [
                    'first' => $products->url(1),
                    'last' => $products->url($products->lastPage()),
                    'prev' => $products->previousPageUrl(),
                    'next' => $products->nextPageUrl(),
                ],
            ]);
    }
}
