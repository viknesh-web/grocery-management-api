<?php

namespace App\Http\Controllers\API;

use App\Helper\DataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductCollection;
use App\Http\Resources\Product\ProductResource;
use App\Services\ProductService;
use App\Validator\ProductValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ProductValidator::onIndex());
        $validator->validate();

        $filters = [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'product_type' => $request->get('product_type'),
            'status' => $request->get('status'),
            'status' => $request->get('status'),
            'has_discount' => $request->get('has_discount'),
            'stock_status' => $request->get('stock_status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $products = $this->productService->getPaginated($filters, $perPage);

        $collection = new ProductCollection($products);
        $responseData = $collection->response()->getData(true);
        
        $filtersApplied = property_exists($products, 'filters_applied') ? $products->filters_applied : [];
        $totalFiltered = property_exists($products, 'total_filtered') ? $products->total_filtered : $products->total();
        
        $response = [
            'data' => $responseData['data'],
            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
                'filters_applied' => $filtersApplied,
                'total_filtered' => $totalFiltered,
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
        ];

        return response()->json($response, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ProductValidator::onCreate());
        $data = $validator->validate();
        $data = DataNormalizer::normalizeProduct($data);

        try {
            $product = $this->productService->create($data, $request->file('image'), $request->user()->id);
            
            $response = [
                'message' => 'Product created successfully',
                'data' => new ProductResource($product),
            ];

            return response()->json($response, 201);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to create product");
        }
    }

    public function show(Request $request): JsonResponse
    {
        $product = $request->get('product');
        
        $response = [
            'data' => new ProductResource($product),
        ];

        return response()->json($response);
    }

    public function update(Request $request): JsonResponse
    {
        $product = $request->get('product');
        $validator = Validator::make($request->all(), ProductValidator::onUpdate($product->id));
        $data = $validator->validate();
        $data = DataNormalizer::normalizeProduct($data, $product->id);

        try {
            $product = $this->productService->update($product, $data, $request->file('image'), $request->boolean('image_removed', false), $request->user()->id);
            
            $response = [
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product),
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to update product");
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $product = $request->get('product');

        try {
            $this->productService->delete($product);
            
            $response = [
                'message' => 'Product deleted successfully',
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            report($e);
            throw new \Exception("Unable to delete product");
        }
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        $product = $request->get('product');
        $product = $this->productService->toggleStatus($product, $request->user()->id);
        
        $response = [
            'message' => 'Product status updated successfully',
            'data' => new ProductResource($product),
        ];

        return response()->json($response);
    }

}
