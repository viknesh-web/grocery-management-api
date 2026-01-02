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

    /**
     * Get paginated list of categories (GET request - for REST API)
     */
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

    /**
     * Get paginated list of categories (POST request - matches reference project pattern)
     * This method accepts POST requests without validation, following hifit-erp-server pattern
     */
    public function list(Request $request)
    {
        $datas = $request->all();

        $flt_page = isset($datas['page']) ? intval($datas['page']) : 0;
        $flt_limit = isset($datas['limit']) ? intval($datas['limit']) : 10;
        $flt_sort = isset($datas['sort']) ? (strtoupper(trim($datas['sort'])) == 'DESC' ? 'DESC' : 'ASC') : 'ASC';
        $flt_orderby = isset($datas['orderby']) ? trim($datas['orderby']) : 'name';
        
        $filters = [
            'status' => $request->get('status'),
            'root' => $request->boolean('root'),
            'parent_id' => $request->get('parent_id'),
            'search' => $request->get('search'),
            'sort_by' => $flt_orderby,
            'sort_order' => strtolower($flt_sort),
        ];

        $perPage = $flt_limit;
        $categories = $this->categoryService->getPaginated($filters, $perPage);

        // Transform to match reference project response format: { data: [], total: number, page: number }
        return response()->json([
            'data' => $categories->items(),
            'total' => $categories->total(),
            'page' => $flt_page
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
        
        return ApiResponse::success($category->toArray(), 'Category created successfully', 201);
    }

    /**
     * Get a single category by ID (REST API - GET /categories/{category})
     */
    public function show(Request $request)
    {
        $category = $request->get('category');
        // $category->load(['parent']);
        
        return ApiResponse::success($category->toArray());
    }

    /**
     * Get category detail (matches reference project pattern - GET /categories/{category}/detail)
     */
    public function detail(Request $request)
    {
        $category = $request->get('category');
        // $category->load(['parent']);
        
        return response()->json([
            'data' => $category->toArray()
        ]);
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
