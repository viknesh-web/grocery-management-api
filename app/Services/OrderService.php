<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Order Service
 * 
 * Handles all business logic for order form operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Form data preparation
 * - Product review processing
 * - Session management
 * - Input validation
 * 
 * Does NOT contain:
 * - Direct Product/Category model queries (uses repositories)
 * - PDF generation (delegated to OrderPdfService)
 */
class OrderService
{
    /**
     * Session key for review products.
     */
    private const SESSION_KEY_REVIEW_PRODUCTS = 'review_products';

    /**
     * Session key for review quantities.
     */
    private const SESSION_KEY_REVIEW_QTY = 'review_qty';

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository
    ) {}

    /**
     * Get form data for order form.
     * 
     * Handles:
     * - Product retrieval (via ProductRepository)
     * - Category retrieval (via CategoryRepository)
     * - Relation eager loading
     *
     * @return array Form data with products and categories
     */
    public function getFormData(): array
    {
        // Get enabled products with categories (business logic - data retrieval via repository)
        $products = $this->productRepository->getActive(['category']);

        // Get categories ordered by name (business logic - data retrieval via repository)
        $categories = $this->categoryRepository->all([], [])
            ->sortBy('name')
            ->values();

        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    /**
     * Process products for review.
     * 
     * Handles:
     * - Input filtering (only products with qty > 0)
     * - Product retrieval (via ProductRepository)
     * - Quantity assignment
     * - Validation
     *
     * @param array $productsInput Form input: [product_id => ['qty' => int], ...]
     * @return Collection Collection of products with qty attribute
     * @throws ValidationException If no valid products selected
     */
    public function processReview(array $productsInput): Collection
    {
        // Filter products with quantity > 0 (business logic - input validation)
        $filtered = collect($productsInput)->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0);
        
        if ($filtered->isEmpty()) {
            throw new ValidationException(
                'Select at least one product',
                ['products' => ['Please select at least one product with quantity greater than 0']]
            );
        }
        
        // Get product IDs (business logic - data extraction)
        $productIds = $filtered->keys()->map(fn($id) => (int) $id)->toArray();

        // Get products via repository (business logic - data retrieval)
        $products = $this->productRepository->findMany($productIds, ['category']);

        // Assign quantities to products (business logic - data transformation)
        $products->each(function ($product) use ($filtered) {
            $product->qty = (int) ($filtered[$product->id]['qty'] ?? 0);
        });

        Log::info('Order review processed', [
            'product_count' => $products->count(),
            'product_ids' => $productIds,
        ]);

        return $products;
    }

    /**
     * Get review products from session.
     * 
     * Handles:
     * - Session retrieval
     * - Data normalization (array to collection)
     * - Validation
     *
     * @return Collection Collection of products from session
     * @throws ValidationException If no products in session
     */
    public function getReviewProducts(): Collection
    {
        $products = session(self::SESSION_KEY_REVIEW_PRODUCTS);
        
        if (!$products || (is_countable($products) && count($products) === 0)) {
            throw new ValidationException(
                'No products in review. Please select products first.',
                ['products' => ['No products selected for review']]
            );
        }
        
        // Normalize to collection (business logic - data normalization)
        if (is_array($products)) {
            $products = collect($products);
        }
        
        return $products;
    }

    /**
     * Clear order session data.
     * 
     * Business logic: Removes all order-related session data.
     *
     * @return void
     */
    public function clearSession(): void
    {
        session()->forget([
            self::SESSION_KEY_REVIEW_PRODUCTS,
            self::SESSION_KEY_REVIEW_QTY,
        ]);

        Log::debug('Order session cleared');
    }

    /**
     * Save review data to session.
     * 
     * Business logic: Stores products and quantities in session for later use.
     *
     * @param Collection $products Products collection
     * @param array $reviewQty Quantities array: [product_id => ['qty' => int], ...]
     * @return void
     */
    public function saveReviewToSession(Collection $products, array $reviewQty): void
    {
        session([
            self::SESSION_KEY_REVIEW_PRODUCTS => $products,
            self::SESSION_KEY_REVIEW_QTY => $reviewQty,
        ]);

        Log::debug('Order review saved to session', [
            'product_count' => $products->count(),
        ]);
    }

    /**
     * Get review quantities from session.
     * 
     * Business logic: Retrieves review quantities from session.
     *
     * @return array Review quantities array
     */
    public function getReviewQuantities(): array
    {
        return session(self::SESSION_KEY_REVIEW_QTY, []);
    }

    /**
     * Check if request is from review page.
     * 
     * Business logic: Determines if request came from review page.
     *
     * @param string|null $fromQuery Query parameter value
     * @return bool
     */
    public function isFromReview(?string $fromQuery): bool
    {
        return $fromQuery === 'review';
    }

    /**
     * Save order confirmation data to session.
     * 
     * Business logic: Stores order confirmation data in session.
     *
     * @param array $data Order confirmation data
     * @return void
     */
    public function saveConfirmationToSession(array $data): void
    {
        session(['order_confirmation' => $data]);

        Log::debug('Order confirmation saved to session');
    }

    /**
     * Get order confirmation data from session.
     * 
     * Business logic: Retrieves order confirmation data from session.
     *
     * @return array|null Order confirmation data or null
     */
    public function getConfirmationFromSession(): ?array
    {
        return session('order_confirmation');
    }
}
