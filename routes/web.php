<?php

use App\Http\Controllers\Web\OrderController;
use Illuminate\Support\Facades\Route;

// Order Form & Review Flow
Route::controller(OrderController::class)->group(function () {
    Route::get('/', 'index')->name('order.form');
    Route::post('/order-review', 'review')->name('order.review');
    Route::get('/order-review', 'showReview')->name('order.review.show');
    Route::post('/order-form/pdf', 'downloadPdf')->name('order.pdf');    
    Route::post('/order/confirmation', 'confirm')->name('order.confirmation.post');
    Route::get('/order/confirmation', 'showConfirmation')->name('order.confirmation');
    Route::get('/api/address/search', 'searchAddress')->name('order.address.search');
});
