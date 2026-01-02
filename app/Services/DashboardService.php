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
 * Handles all business logic for dashboard operations.
 * 
 * Responsibilities:
 * - Dashboard statistics calculation
 * - Data aggregation
 * - Recent activity tracking
 * 
 * Does NOT contain:
 * - Direct model queries (uses repositories)
 */
class DashboardService
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private PriceUpdateRepository $priceUpdateRepository
    ) {}

    /**
     * Get dashboard statistics.
     * 
     * Handles:
     * - Product statistics (via ProductRepository)
     * - Category statistics (via CategoryRepository)
     * - Customer statistics (via CustomerRepository)
     * - Price update statistics (via PriceUpdateRepository)
     * - Recent price changes
     *
     * @return array Dashboard statistics
     */
    public function getStatistics(): array
    {
        // Product statistics (business logic - data aggregation)
        $productStats = $this->getProductStatistics();
        
        // Category statistics (business logic - data aggregation)
        $categoryStats = $this->getCategoryStatistics();
        
        // Customer statistics (business logic - data aggregation)
        $customerStats = $this->getCustomerStatistics();
        
        // Price update statistics (business logic - data aggregation)
        $priceUpdateStats = $this->getPriceUpdateStatistics();
        
        // Low stock products (business logic - data aggregation)
        $lowStockProducts = $this->getLowStockProductsCount();
        
        // Product type statistics (business logic - data aggregation)
        $productTypeStats = $this->getProductTypeStatistics();
        
        // Recent price changes (business logic - data retrieval)
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

    /**
     * Get product statistics.
     * 
     * Business logic: Calculates product counts by status.
     *
     * @return array
     */
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

    /**
     * Get category statistics.
     * 
     * Business logic: Calculates category counts by status.
     *
     * @return array
     */
    protected function getCategoryStatistics(): array
    {
        $totalCategories = $this->categoryRepository->count();
        $activeCategories = $this->categoryRepository->countByFilters(['status' => 'active']);

        return [
            'total' => $totalCategories,
            'active' => $activeCategories,
            'inactive' => $totalCategories - $activeCategories,
        ];
    }

    /**
     * Get customer statistics.
     * 
     * Business logic: Calculates customer counts by status.
     *
     * @return array
     */
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

    /**
     * Get price update statistics.
     * 
     * Business logic: Calculates price updates in last 7 days.
     *
     * @return array
     */
    protected function getPriceUpdateStatistics(): array
    {
        $since = Carbon::now()->subDays(7);
        $recentPriceUpdates = $this->priceUpdateRepository->countSince($since);

        return [
            'last_7_days' => $recentPriceUpdates,
        ];
    }

    /**
     * Get low stock products count.
     * 
     * Business logic: Counts active products with stock < 10.
     *
     * @return int
     */
    protected function getLowStockProductsCount(): int
    {
        return $this->productRepository->countByFilters([
            'status' => 'active',
            'stock_status' => 'low_stock',
        ]);
    }

    /**
     * Get product type statistics.
     * 
     * Business logic: Calculates product counts by type.
     *
     * @return array
     */
    protected function getProductTypeStatistics(): array
    {
        $dailyProducts = $this->productRepository->countByFilters(['product_type' => 'daily']);
        $standardProducts = $this->productRepository->countByFilters(['product_type' => 'standard']);

        return [
            'daily' => $dailyProducts,
            'standard' => $standardProducts,
        ];
    }

    /**
     * Get recent price changes.
     * 
     * Business logic: Retrieves recent price updates with product relation.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getRecentPriceChanges(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $relations = ['product'];
        return $this->priceUpdateRepository->getRecent($limit, $relations);
    }
}

