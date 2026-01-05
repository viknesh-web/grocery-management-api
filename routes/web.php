<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\Web\OrderFormController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrderFormController::class, 'show']) ->name('order.form');
Route::post('/order-form/pdf', [OrderFormController::class, 'downloadPdf']) ->name('order.pdf');
Route::post('/order-review', [OrderFormController::class, 'review'])->name('order.review');
Route::post('/order/confirmation', [OrderFormController::class, 'confirm'])->name('order.confirmation');
Route::get('/order/confirmation', [OrderFormController::class, 'showConfirmation'])->name('order.confirmation.show');
Route::get('/order-review', [OrderFormController::class, 'showReview'])     ->name('order.review.show');
Route::get('/geoapify/address', [AddressController::class, 'searchUAE']);