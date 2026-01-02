<?php

namespace App\Http\Controllers\API;

use App\Exceptions\MessageException;
use App\Helper\DataNormalizer;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Traits\HasImageUpload;
use App\Http\Traits\HasStatusToggle;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use HasImageUpload, HasStatusToggle;

    public function __construct(
        private ProductService $productService
    ) {}

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'product_type' => ['sometimes', 'string', 'in:daily,standard'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'has_discount' => ['sometimes', 'boolean'],
            'stock_status' => ['sometimes', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['sometimes', 'string', Rule::in(['name', 'regular_price', 'selling_price', 'stock_quantity', 'created_at', 'product_type'])],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $validator->validate();

        $filters = [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'product_type' => $request->get('product_type'),
            'status' => $request->get('status'),
            'has_discount' => $request->get('has_discount'),
            'stock_status' => $request->get('stock_status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $products = $this->productService->getPaginated($filters, $perPage);
        
        $filtersApplied = property_exists($products, 'filters_applied') ? $products->filters_applied : [];
        $totalFiltered = property_exists($products, 'total_filtered') ? $products->total_filtered : $products->total();
        
        return ApiResponse::paginated($products, [
            'filters_applied' => $filtersApplied,
            'total_filtered' => $totalFiltered,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validated();
        $product = $this->productService->create($data, $request->file('image'), $request->user()->id);
        
        $validator = Validator::make($request->all(), ProductValidator::onCreate());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeProduct($data);

        try {
            $product = $this->productService->create($data, $request->file('image'), $request->user()->id);
            
            return ApiResponse::success($product, 'Product created successfully', 201);
        } catch (\Exception $e) {
            report($e);
            throw new MessageException("Unable to create product");
        }
    }

    public function show(Request $request)
    {
        $product = $request->get('product');
        $product->load(['creator', 'updater', 'category']);
        
        return ApiResponse::success($product);
    }

    public function update(Request $request)
    {
        $product = $request->get('product');
        $product = $this->toggleModelStatus($product, $this->productService, $request->user()->id);
        
        if ($request->has('enabled')) {
            $request->merge(['enabled' => $request->boolean('enabled')]);
        }
        
        $validator = Validator::make($request->all(), ProductValidator::onUpdate($product->id));
        $data = $validator->validate();
        $data = DataNormalizer::normalizeProduct($data, $product->id);

        try {
            $product = $this->productService->update($product, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
            
            return ApiResponse::success($product, 'Product updated successfully');
        } catch (\Exception $e) {
            report($e);
            throw new MessageException("Unable to update product");
        }
    }

    public function destroy(Request $request)
    {
        $product = $request->get('product');

        try {
            $this->productService->delete($product);
            
            return ApiResponse::success(null, 'Product deleted successfully');
        } catch (\Exception $e) {
            report($e);
            throw new App\Http\Controllers\API\MessageException("Unable to delete product");
        }
    }

    public function toggleStatus(Request $request)
    {
        $product = $request->get('product');
        $product = $this->toggleModelStatus($product, $this->productService, $request->user()->id);
        
        return ApiResponse::success($product, 'Product status updated successfully');
    }

}
