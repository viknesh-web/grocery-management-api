<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Repositories\ProductRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;

/**
 * Order PDF Service
 * 
 */
class OrderPdfService
{
    /**
     * Session key for review products.
     */
    private const SESSION_KEY_REVIEW_PRODUCTS = 'review_products';   
    private const PDF_PAPER_SIZE = 'A4';
    private const PDF_ORIENTATION = 'portrait';

    public function __construct(
        private ProductRepository $productRepository
    ) {}

    /**
     * Generate order PDF and return download response.
     */
    public function generate($productsInput = null)
    {
        $products = $this->normalizeProductsInput($productsInput);
        if ($products->isEmpty()) {
            throw new BusinessException('No products to generate PDF');
        }

        $pdf = $this->generatePdf($products);
        $filename = $this->generateFilename();

        Log::info('Order PDF generated', [
            'product_count' => $products->count(),
            'filename' => $filename,
        ]);

        return $pdf->download($filename);
    }
    
    protected function normalizeProductsInput($productsInput): Collection|SupportCollection
    {
        if (empty($productsInput)) {
            return $this->getProductsFromSession();
        }
        if (is_array($productsInput) && $this->looksLikeFormInput($productsInput)) {
            return $this->getProductsFromFormInput($productsInput);
        }

        // Convert to Support Collection if it's an array
        return collect($productsInput);
    }
   
    protected function getProductsFromSession(): SupportCollection
    {
        $products = session(self::SESSION_KEY_REVIEW_PRODUCTS, collect());
        
        if (is_array($products)) {
            $products = collect($products);
        }
        
        return $products;
    }
  
    protected function getProductsFromFormInput(array $formInput): Collection
    {
        $productIds = array_keys($formInput);
        $productIds = array_map(fn($id) => (int) $id, $productIds);
        $products = $this->productRepository->findMany($productIds, ['category']);
        $products->each(function ($product) use ($formInput) {
            $product->qty = (int) ($formInput[$product->id]['qty'] ?? 0);
        });

        return $products;
    }

    protected function looksLikeFormInput(array $input): bool
    {
        foreach ($input as $key => $value) {
            if (!is_array($value)) {
                return false;
            }
            if (!array_key_exists('qty', $value)) {
                return false;
            }
            break;
        }

        return true;
    }

    protected function generatePdf(Collection|SupportCollection $products)
    {
        $pdf = Pdf::loadView('order.pdf', ['products' => $products]);
        $pdf->setPaper(self::PDF_PAPER_SIZE, self::PDF_ORIENTATION);

        return $pdf;
    }

    protected function generateFilename(): string
    {
        return 'order-' . now()->format('Y-m-d-H-i-s') . '.pdf';
    }
}
 