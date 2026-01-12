<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PriceUpdateController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\WhatsAppController;
use Illuminate\Support\Facades\Route;



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

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('categories')->middleware([\App\Http\Middleware\CheckCategory::class])->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::post('/reorder', [CategoryController::class, 'reorder']);
        Route::get('/search/{query}', [CategoryController::class, 'search']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::group(['prefix' => '/{category}'], function () {
            Route::get('/', [CategoryController::class, 'show']);
            Route::post('/update', [CategoryController::class, 'update']);
            Route::delete('/', [CategoryController::class, 'destroy']);
            Route::post('/toggle-status', [CategoryController::class, 'toggleStatus']);
            Route::get('/products', [CategoryController::class, 'products']);
        });
    });

    Route::prefix('products')->middleware([\App\Http\Middleware\CheckProduct::class])->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::group(['prefix' => '/{product}'], function () {
            Route::get('/', [ProductController::class, 'show']);
            Route::post('/update', [ProductController::class, 'update']);
            Route::delete('/', [ProductController::class, 'destroy']);
            Route::post('/toggle-status', [ProductController::class, 'toggleStatus']);
        });
    });

    Route::prefix('customers')->middleware([\App\Http\Middleware\CheckCustomer::class])->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::group(['prefix' => '/{customer}'], function () {
            Route::get('/', [CustomerController::class, 'show']);
            Route::post('/edit', [CustomerController::class, 'update']);
            Route::delete('/', [CustomerController::class, 'destroy']);
            Route::post('/toggle-status', [CustomerController::class, 'toggleStatus']);
        });
    });  

    Route::prefix('orders')->middleware([\App\Http\Middleware\CheckOrder::class])->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/statistics', [OrderController::class, 'statistics']);
        Route::get('/revenue-stats', [OrderController::class, 'revenueStats']);
        Route::get('/customer/{customerId}', [OrderController::class, 'customerOrders']);
        Route::post('/generate-url', [OrderController::class, 'generateOrderUrl']);
        
        Route::group(['prefix' => '/{order}'], function () {
            Route::get('/', [OrderController::class, 'show']);
            Route::delete('/', [OrderController::class, 'destroy']);
            Route::post('/status', [OrderController::class, 'updateStatus']);
            Route::post('/cancel', [OrderController::class, 'cancel']);
        });
    });

    Route::get('/addresses/search-uae', [AddressController::class, 'searchUAE']);

    Route::get('/price-updates/products', [PriceUpdateController::class, 'getProducts']);
    Route::post('/price-updates/bulk-update', [PriceUpdateController::class, 'bulkUpdate']);
    Route::get('/price-updates/product/{product}/history', [PriceUpdateController::class, 'productHistory'])->middleware(\App\Http\Middleware\CheckProduct::class);
    Route::get('/price-updates/by-date-range', [PriceUpdateController::class, 'byDateRange']);
    Route::get('/price-updates/recent', [PriceUpdateController::class, 'recent']);

    Route::post('/whatsapp/generate-price-list', [WhatsAppController::class, 'generatePriceList']);
    Route::post('/whatsapp/send-message', [WhatsAppController::class, 'sendMessage']);
    Route::post('/whatsapp/send-product-update', [WhatsAppController::class, 'sendProductUpdate']);
    Route::post('/whatsapp/test-message/{customer}', [WhatsAppController::class, 'sendTestMessage'])->middleware(\App\Http\Middleware\CheckCustomer::class);
    Route::post('/whatsapp/validate-number', [WhatsAppController::class, 'validateNumber']);  

   

});


