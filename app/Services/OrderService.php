<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\Collection;

class OrderService
{
    public function __construct(
        private OrderRepository $repository
    ) {}

    public function getFormData(): array
    {
        $products = $this->repository->getEnabledProductsWithCategories();
        $categories = $this->repository->getCategoriesOrdered();
        
        return [
            'products' => $products,
            'categories' => $categories,
        ];
    }

    public function processReview(array $productsInput): Collection
    {
        $filtered = collect($productsInput)->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0);
        
        if ($filtered->isEmpty()) {
            throw new \Exception('Select at least one product');
        }
        
        $productIds = $filtered->keys()->toArray();
        $products = $this->repository->getProductsByIds($productIds);
        
        $products->each(function ($product) use ($filtered) {
            $product->qty = $filtered[$product->id]['qty'];
        });
        
        return $products;
    }

    public function getReviewProducts(): Collection
    {
        $products = session('review_products');
        
        if (!$products || (is_countable($products) && count($products) === 0)) {
            throw new \Exception('No products in review. Please select products first.');
        }
        
        if (is_array($products)) {
            $products = collect($products);
        }
        
        return $products;
    }

    public function clearSession(): void
    {
        session()->forget(['review_products', 'review_qty']);
    }

    public function saveReviewToSession(Collection $products, array $reviewQty): void
    {
        session([
            'review_products' => $products,
            'review_qty' => $reviewQty
        ]);
    }
}

