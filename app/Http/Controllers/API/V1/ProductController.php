<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductCollection;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Product Controller
 * 
 * Handles HTTP requests for product management operations.
 * Business logic is delegated to ProductService.
 */
class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    /**
     * Display a listing of products with advanced filtering.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate query parameters
        $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'product_type' => ['sometimes', 'string', 'in:daily,standard'],
            'status' => ['sometimes', 'string', 'in:enabled,disabled'],
            'enabled' => ['sometimes', 'boolean'],
            'has_discount' => ['sometimes', 'boolean'],
            'stock_status' => ['sometimes', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['sometimes', 'string', 'in:name,original_price,selling_price,stock_quantity,created_at,product_type'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'product_type' => $request->get('product_type'),
            'status' => $request->get('status'),
            'enabled' => $request->get('enabled'),
            'has_discount' => $request->get('has_discount'),
            'stock_status' => $request->get('stock_status'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        $perPage = $request->get('per_page', 15);
        $products = $this->productService->getPaginated($filters, $perPage);

        $collection = new ProductCollection($products);
        $response = $collection->response()->getData(true);
        
        // Get metadata from service
        $filtersApplied = property_exists($products, 'filters_applied') ? $products->filters_applied : [];
        $totalFiltered = property_exists($products, 'total_filtered') ? $products->total_filtered : $products->total();
        
        return response()->json([
            'data' => $response['data'],
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
        ], 200);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->create(
                $request->validated(),
                $request->file('image'),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Product created successfully',
                'data' => new ProductResource($product),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        $product = $this->productService->find($product->id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $product = $this->productService->update(
                $product,
                $request->validated(),
                $request->file('image'),
                $request->boolean('image_removed', false),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->productService->delete($product);

            return response()->json([
                'message' => 'Product deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle product enabled status.
     */
    public function toggleStatus(Request $request, Product $product): JsonResponse
    {
        $product = $this->productService->toggleStatus($product, $request->user()->id);

        return response()->json([
            'message' => 'Product status updated successfully',
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Get variations for a product.
     */
    public function getVariations(Product $product): JsonResponse
    {
        $variations = $product->activeVariations()->get();

        return response()->json([
            'data' => $variations->map(function ($variation) {
                return [
                    'id' => $variation->id,
                    'quantity' => $variation->quantity,
                    'unit' => $variation->unit,
                    'display_name' => $variation->display_name,
                    'price' => $variation->price,
                    'stock_quantity' => $variation->stock_quantity,
                    'sku' => $variation->sku,
                    'enabled' => $variation->enabled,
                    'is_in_stock' => $variation->isInStock(),
                ];
            }),
        ], 200);
    }
}
