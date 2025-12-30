<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PriceUpdate;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Controller
 * 
 * Provides dashboard statistics and analytics data.
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $totalCategories = Category::count();
        $activeCategories = Category::active()->count();
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::active()->count();

        // Recent price updates (last 7 days)
        $recentPriceUpdates = PriceUpdate::where('created_at', '>=', now()->subDays(7))->count();

        // Low stock products (less than 10)
        $lowStockProducts = Product::where('stock_quantity', '<', 10)->where('status', 'active')->count();

        // Product type statistics
        $dailyProducts = Product::where('product_type', 'daily')->count();
        $standardProducts = Product::where('product_type', 'standard')->count();

        // Recent price changes
        $recentPriceChanges = PriceUpdate::with(['product:id,name,item_code'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($update) {
                return [
                    'id' => $update->id,
                    'product' => [
                        'id' => $update->product->id,
                        'name' => $update->product->name,
                        'item_code' => $update->product->item_code,
                    ],
                    'old_regular_price' => $update->old_regular_price,
                    'new_regular_price' => $update->new_regular_price,
                    'price_change_percentage' => $update->price_change_percentage,
                    'updated_at' => $update->created_at->toISOString(),
                ];
            });

            $data = [
                'statistics' => [
                    'products' => [
                        'total' => $totalProducts,
                        'active' => $activeProducts,
                        'inactive' => $totalProducts - $activeProducts,
                    ],
                    'categories' => [
                        'total' => $totalCategories,
                        'active' => $activeCategories,
                        'inactive' => $totalCategories - $activeCategories,
                    ],
                    'customers' => [
                        'total' => $totalCustomers,
                        'active' => $activeCustomers,
                        'inactive' => $totalCustomers - $activeCustomers,
                    ],
                    'price_updates' => [
                        'last_7_days' => $recentPriceUpdates,
                    ],
                    'low_stock_products' => $lowStockProducts,
                    'product_types' => [
                        'daily' => $dailyProducts,
                        'standard' => $standardProducts,
                    ],
                ],
                'recent_price_changes' => $recentPriceChanges,
            ];

        return response()->json([
            'data' => $data,
        ], 200);
    }
}



