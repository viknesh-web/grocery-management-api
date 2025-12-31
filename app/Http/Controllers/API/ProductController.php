<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
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
        
        return (new ProductCollection($products, $filtersApplied, $totalFiltered))
            ->additional([
                'meta' => array_merge([
                    'current_page' => $products->currentPage(),
                    'from' => $products->firstItem(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ], [
                    'filters_applied' => $filtersApplied,
                    'total_filtered' => $totalFiltered,
                ]),
                'links' => [
                    'first' => $products->url(1),
                    'last' => $products->url($products->lastPage()),
                    'prev' => $products->previousPageUrl(),
                    'next' => $products->nextPageUrl(),
                ],
            ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $product = $this->productService->create($data, $request->file('image'), $request->user()->id);
        
        return (new ProductResource($product))
            ->additional(['message' => 'Product created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request)
    {
        $product = $request->get('product');
        $product->load(['creator', 'updater', 'category']);
        
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request)
    {
        $product = $request->get('product');
        $data = $request->validated();
        $product = $this->productService->update($product, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
        
        return (new ProductResource($product))
            ->additional(['message' => 'Product updated successfully']);
    }

    public function destroy(Request $request)
    {
        $product = $request->get('product');
        $this->productService->delete($product);
        
        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    public function toggleStatus(Request $request)
    {
        $product = $request->get('product');
        $product = $this->toggleModelStatus($product, $this->productService, $request->user()->id);
        
        return (new ProductResource($product))
            ->additional(['message' => 'Product status updated successfully']);
    }

}
