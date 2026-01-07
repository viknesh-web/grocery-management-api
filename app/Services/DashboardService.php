<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PriceUpdateRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Carbon;

/**
 * Dashboard Service
 * 
 */
class DashboardService
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private PriceUpdateRepository $priceUpdateRepository
    ) {}
 
    public function getStatistics(): array
    {
        $productStats = $this->getProductStatistics();
        $categoryStats = $this->getCategoryStatistics();        
        $customerStats = $this->getCustomerStatistics();
        $priceUpdateStats = $this->getPriceUpdateStatistics();
        $lowStockProducts = $this->getLowStockProductsCount();
        $productTypeStats = $this->getProductTypeStatistics();
        $recentPriceChanges = $this->getRecentPriceChanges();

        return [
            'statistics' => [
                'products' => $productStats,
                'categories' => $categoryStats,
                'customers' => $customerStats,
                'price_updates' => $priceUpdateStats,
                'low_stock_products' => $lowStockProducts,
                'product_types' => $productTypeStats,
            ],
            'recent_price_changes' => $recentPriceChanges,
        ];
    }
   
    protected function getProductStatistics(): array
    {
        $totalProducts = $this->productRepository->count();
        $activeProducts = $this->productRepository->countByFilters(['status' => 'active']);

        return [
            'total' => $totalProducts,
            'active' => $activeProducts,
            'inactive' => $totalProducts - $activeProducts,
        ];
    }

    protected function getCategoryStatistics(): array
    {
        $category = $this->categoryRepository->all();
        $categoryproduct = $this->categoryRepository->getNameAndProductCount();
        $totalCategories = $this->categoryRepository->count();
        $activeCategories = $this->categoryRepository->countByFilters(['status' => 'active']);

        return [
            'data' => $category,
            'product' => $categoryproduct,
            'total' => $totalCategories,
            'active' => $activeCategories,
            'inactive' => $totalCategories - $activeCategories,
        ];
    }

    protected function getCustomerStatistics(): array
    {
        $totalCustomers = $this->customerRepository->count();
        $activeCustomers = $this->customerRepository->countByFilters(['status' => 'active']);

        return [
            'total' => $totalCustomers,
            'active' => $activeCustomers,
            'inactive' => $totalCustomers - $activeCustomers,
        ];
    }

  
    protected function getPriceUpdateStatistics(): array
    {
        $since = Carbon::now()->subDays(7);
        $recentPriceUpdates = $this->priceUpdateRepository->countSince($since);

        return [
            'last_7_days' => $recentPriceUpdates,
        ];
    }

    protected function getLowStockProductsCount(): int
    {
        return $this->productRepository->countByFilters([
            'status' => 'active',
            'stock_status' => 'low_stock',
        ]);
    }

    protected function getProductTypeStatistics(): array
    {
        $dailyProducts = $this->productRepository->countByFilters(['product_type' => 'daily']);
        $standardProducts = $this->productRepository->countByFilters(['product_type' => 'standard']);

        return [
            'daily' => $dailyProducts,
            'standard' => $standardProducts,
        ];
    }


    protected function getRecentPriceChanges(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $relations = ['product'];
        return $this->priceUpdateRepository->getRecent($limit, $relations);
    }
}

