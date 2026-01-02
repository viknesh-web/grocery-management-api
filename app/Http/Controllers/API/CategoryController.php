<?php

namespace App\Http\Controllers\API;

use App\Exceptions\MessageException;
use App\Helper\DataNormalizer;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductCollection;
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

    public function store(Request $request)
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

        try {
            $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
            
            return ApiResponse::success($category, 'Category created successfully', 201);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to create category");
        }
    }

    public function show(Request $request)
    {
        $category = $request->get('category');
        $category->load(['creator', 'updater', 'parent']);
        
        return ApiResponse::success($category);
    }

    public function update(Request $request)
    {
        $data = $request->validated();
        $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
        
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }
        
        $validator = Validator::make($request->all(), CategoryValidator::onUpdate($category->id));
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCategory($data, $category->id);

        try {
            $category = $this->categoryService->update($category, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
            
            return ApiResponse::success($category, 'Category updated successfully');
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to update category");
        }
    }

    public function destroy(Request $request)
    {
        $category = $request->get('category');

        try {
            $this->categoryService->delete($category);
            
            return ApiResponse::success(null, 'Category deleted successfully');
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to delete category");
        }
    }

    public function toggleStatus(Request $request)
    {
        $category = $request->get('category');
        $category = $this->toggleModelStatus($category, $this->categoryService, $request->user()->id);
        
        return ApiResponse::success($category, 'Category status updated successfully');
    }

    public function reorder(Request $request)
    {
        // Display order is no longer supported in the schema
        // This endpoint is kept for API compatibility but does nothing
        return ApiResponse::success(null, 'Category reordering is no longer supported');
    }

    public function search(Request $request, string $query)
    {
        $categories = $this->categoryService->search($query);

        return ApiResponse::success($categories);
    }

    public function tree(Request $request)
    {
        $categories = $this->categoryService->getTree();
        
        return ApiResponse::success($categories);
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
