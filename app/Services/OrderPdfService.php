<?php

namespace App\Services;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPdfService
{
    /**
     * Generate an order PDF and return a download response.
     *
     * @param mixed $productsInput Array/Collection of products or form input (ids => ['qty'=>x])
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     */
    public function generate($productsInput = null)
    {
        // 1) If no input provided, try to get products from session (review flow)
        if (empty($productsInput)) {
            $products = session('review_products', collect());
            if (is_array($products)) {
                $products = collect($products);
            }
        } else {
            // 2) If input is an array of form values like [id => ['qty' => x], ...]
            if (is_array($productsInput) && $this->looksLikeFormInput($productsInput)) {
                $productIds = array_keys($productsInput);
                $products = Product::whereIn('id', $productIds)->get();
                $products->each(function ($product) use ($productsInput) {
                    $product->qty = $productsInput[$product->id]['qty'] ?? 0;
                });
            } else {
                // 3) If input is a collection or array of Product-like objects, normalize to collection
                $products = collect($productsInput);
            }
        }

        // 4) Validate
        if ($products->isEmpty()) {
            abort(400, 'No products to generate PDF');
        }

        // 5) Render PDF using existing `pdf` view
        $pdf = Pdf::loadView('order.pdf', ['products' => $products]);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'order-' . now()->format('Y-m-d-H-i-s') . '.pdf';

        return $pdf->download($filename);
    }

    private function looksLikeFormInput(array $input): bool
    {
        // typical form input is keyed by product id and contains a 'qty' field
        foreach ($input as $key => $value) {
            if (!is_array($value)) {
                return false;
            }
            if (!array_key_exists('qty', $value)) {
                return false;
            }
            // only check first element
            break;
        }

        return true;
    }
}
