<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Repositories\ProductRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Order PDF Service
 * 
 * Handles all business logic for order PDF generation.
 * 
 * Responsibilities:
 * - PDF generation logic
 * - Product data preparation
 * - Input normalization
 * - Session data handling
 * 
 * Does NOT contain:
 * - Direct Product model queries (uses ProductRepository)
 * - File storage (PDF is streamed directly)
 */
class OrderPdfService
{
    /**
     * Session key for review products.
     */
    private const SESSION_KEY_REVIEW_PRODUCTS = 'review_products';

    /**
     * Default PDF paper size.
     */
    private const PDF_PAPER_SIZE = 'A4';

    /**
     * Default PDF orientation.
     */
    private const PDF_ORIENTATION = 'portrait';

    public function __construct(
        private ProductRepository $productRepository
    ) {}

    /**
     * Generate order PDF and return download response.
     * 
     * Handles:
     * - Input normalization (form input, collection, or session)
     * - Product retrieval (via ProductRepository if needed)
     * - PDF generation
     * - Download response
     *
     * @param mixed $productsInput Array/Collection of products or form input (ids => ['qty'=>x])
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     * @throws BusinessException If no products to generate PDF
     */
    public function generate($productsInput = null)
    {
        // Normalize products input (business logic - input handling)
        $products = $this->normalizeProductsInput($productsInput);

        // Validate products (business logic - validation)
        if ($products->isEmpty()) {
            throw new BusinessException('No products to generate PDF');
        }

        // Generate PDF (business logic - PDF generation)
        $pdf = $this->generatePdf($products);

        // Generate filename (business logic - file naming)
        $filename = $this->generateFilename();

        Log::info('Order PDF generated', [
            'product_count' => $products->count(),
            'filename' => $filename,
        ]);

        return $pdf->download($filename);
    }

    /**
     * Normalize products input to collection.
     * 
     * Business logic: Handles various input formats and converts to collection.
     *
     * @param mixed $productsInput Form input, collection, or null (uses session)
     * @return Collection Collection of products with qty attribute
     */
    protected function normalizeProductsInput($productsInput): Collection
    {
        // 1) If no input provided, try to get products from session (review flow)
        if (empty($productsInput)) {
            return $this->getProductsFromSession();
        }

        // 2) If input is an array of form values like [id => ['qty' => x], ...]
        if (is_array($productsInput) && $this->looksLikeFormInput($productsInput)) {
            return $this->getProductsFromFormInput($productsInput);
        }

        // 3) If input is a collection or array of Product-like objects, normalize to collection
        return collect($productsInput);
    }

    /**
     * Get products from session.
     * 
     * Business logic: Retrieves products from session and normalizes to collection.
     *
     * @return Collection
     */
    protected function getProductsFromSession(): Collection
    {
        $products = session(self::SESSION_KEY_REVIEW_PRODUCTS, collect());
        
        if (is_array($products)) {
            $products = collect($products);
        }
        
        return $products;
    }

    /**
     * Get products from form input.
     * 
     * Business logic: Retrieves products via repository and assigns quantities.
     *
     * @param array $formInput Form input: [product_id => ['qty' => int], ...]
     * @return Collection Collection of products with qty attribute
     */
    protected function getProductsFromFormInput(array $formInput): Collection
    {
        // Extract product IDs (business logic - data extraction)
        $productIds = array_keys($formInput);
        $productIds = array_map(fn($id) => (int) $id, $productIds);

        // Get products via repository (business logic - data retrieval)
        $products = $this->productRepository->findMany($productIds, ['category']);

        // Assign quantities to products (business logic - data transformation)
        $products->each(function ($product) use ($formInput) {
            $product->qty = (int) ($formInput[$product->id]['qty'] ?? 0);
        });

        return $products;
    }

    /**
     * Check if input looks like form input.
     * 
     * Business logic: Determines if input is form data format.
     *
     * @param array $input
     * @return bool
     */
    protected function looksLikeFormInput(array $input): bool
    {
        // Typical form input is keyed by product id and contains a 'qty' field
        foreach ($input as $key => $value) {
            if (!is_array($value)) {
                return false;
            }
            if (!array_key_exists('qty', $value)) {
                return false;
            }
            // Only check first element
            break;
        }

        return true;
    }

    /**
     * Generate PDF from products.
     * 
     * Business logic: Creates PDF using order template.
     *
     * @param Collection $products Products collection with qty attribute
     * @return \Barryvdh\DomPDF\PDF
     */
    protected function generatePdf(Collection $products)
    {
        // Render PDF using order template (business logic - PDF generation)
        $pdf = Pdf::loadView('order.pdf', ['products' => $products]);
        $pdf->setPaper(self::PDF_PAPER_SIZE, self::PDF_ORIENTATION);

        return $pdf;
    }

    /**
     * Generate filename for order PDF.
     * 
     * Business logic: Creates unique filename based on timestamp.
     *
     * @return string Filename
     */
    protected function generateFilename(): string
    {
        return 'order-' . now()->format('Y-m-d-H-i-s') . '.pdf';
    }
}
