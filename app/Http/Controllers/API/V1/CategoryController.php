<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\MessageException;
use App\Helper\DataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Category\CategoryCollection;
use App\Http\Resources\Product\ProductCollection;
use App\Services\CategoryService;
use App\Validator\CategoryValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

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

    public function store(Request $request): JsonResponse
    {
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }
        
        $validator = Validator::make($request->all(), CategoryValidator::onCreate());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCategory($data);

        try {
            $category = $this->categoryService->create($data, $request->file('image'), $request->user()->id);
            
            $response = [
                'message' => 'Category created successfully',
                'data' => $category,
            ];

            return response()->json($response, 201);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to create category");
        }
    }

    public function show(Request $request): JsonResponse
    {
        $category = $request->get('category');
        $category->load(['creator', 'updater', 'parent']);
        
        return response()->json([
            'data' => $category,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $category = $request->get('category');
        
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }
        
        $validator = Validator::make($request->all(), CategoryValidator::onUpdate($category->id));
        $data = $validator->validate();
        $data = DataNormalizer::normalizeCategory($data, $category->id);

        try {
            $category = $this->categoryService->update($category, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
            
            $response = [
                'message' => 'Category updated successfully',
                'data' => $category,
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to update category");
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $category = $request->get('category');

        try {
            $this->categoryService->delete($category);
            
            $response = [
                'message' => 'Category deleted successfully',
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to delete category");
        }
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        $category = $request->get('category');
        $category = $this->categoryService->toggleStatus($category, $request->user()->id);
        
        $response = [
            'message' => 'Category status updated successfully',
            'data' => $category,
        ];

        return response()->json($response);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.display_order' => ['required', 'integer', 'min:0'],
        ]);

        $this->categoryService->reorder($request->categories);
        
        $response = [
            'message' => 'Categories reordered successfully',
        ];

        return response()->json($response);
    }

    public function search(Request $request, string $query): JsonResponse
    {
        $categories = $this->categoryService->search($query);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function tree(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getTree();
        
        $response = [
            'data' => $categories,
        ];

        return response()->json($response);
    }

    public function products(Request $request): ProductCollection
    {
        $category = $request->get('category');
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
