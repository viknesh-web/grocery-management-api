<?php

use App\Http\Controllers\API\V1\AddressController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\CategoryController;
use App\Http\Controllers\API\V1\CustomerController;
use App\Http\Controllers\API\V1\DashboardController;
use App\Http\Controllers\API\V1\OrderFormController;
use App\Http\Controllers\API\V1\PriceUpdateController;
use App\Http\Controllers\API\V1\ProductController;
use App\Http\Controllers\API\V1\WhatsAppController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Categories
    Route::group(['prefix' => 'categories'], function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::post('/reorder', [CategoryController::class, 'reorder']);
        Route::get('/search/{query}', [CategoryController::class, 'search']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::group(['prefix' => '/{category}'], function () {
            Route::get('/', [CategoryController::class, 'show']);
            Route::put('/', [CategoryController::class, 'update']);
            Route::delete('/', [CategoryController::class, 'destroy']);
            Route::post('/toggle-status', [CategoryController::class, 'toggleStatus']);
            Route::get('/products', [CategoryController::class, 'products']);
        });
    });

    // Products
    Route::group(['prefix' => 'products'], function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::group(['prefix' => '/{product}'], function () {
            Route::get('/', [ProductController::class, 'show']);
            Route::put('/', [ProductController::class, 'update']);
            Route::delete('/', [ProductController::class, 'destroy']);
            Route::post('/toggle-status', [ProductController::class, 'toggleStatus']);
            Route::get('/variations', [ProductController::class, 'getVariations']);
        });
    });

    // Customers
    Route::group(['prefix' => 'customers'], function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::group(['prefix' => '/{customer}'], function () {
            Route::get('/', [CustomerController::class, 'show']);
            Route::put('/', [CustomerController::class, 'update']);
            Route::delete('/', [CustomerController::class, 'destroy']);
            Route::post('/toggle-status', [CustomerController::class, 'toggleStatus']);
        });
    });

    // Addresses (feature-flagged)
    if (config('features.address_field')) {
        Route::apiResource('addresses', AddressController::class);
    }

    // UAE Address Search (always available, not feature-flagged)
    Route::get('/addresses/search-uae', [AddressController::class, 'searchUAE']);

    // Price Updates
    Route::get('/price-updates/products', [PriceUpdateController::class, 'getProducts']);
    Route::post('/price-updates/bulk-update', [PriceUpdateController::class, 'bulkUpdate']);
    Route::get('/price-updates/product/{product}/history', [PriceUpdateController::class, 'productHistory']);
    Route::get('/price-updates/by-date-range', [PriceUpdateController::class, 'byDateRange']);
    Route::get('/price-updates/recent', [PriceUpdateController::class, 'recent']);

    // WhatsApp
    Route::post('/whatsapp/generate-price-list', [WhatsAppController::class, 'generatePriceList']);
    Route::post('/whatsapp/send-message', [WhatsAppController::class, 'sendMessage']);
    Route::post('/whatsapp/send-product-update', [WhatsAppController::class, 'sendProductUpdate']);
    Route::post('/whatsapp/test-message/{customer}', [WhatsAppController::class, 'sendTestMessage']);
    Route::post('/whatsapp/validate-number', [WhatsAppController::class, 'validateNumber']);

});
Route::get('/order-form', [OrderFormController::class, 'show']) ->name('order.form');
Route::post('/order-form/pdf', [OrderFormController::class, 'downloadPdf']) ->name('order.pdf');
Route::post('/order-review', [OrderFormController::class, 'review'])->name('order.review');
Route::post('/order/confirmation', [OrderFormController::class, 'confirm'])->name('order.confirmation');


