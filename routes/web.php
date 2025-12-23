<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\OrderFormController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/order-form', [OrderFormController::class, 'show']) ->name('order.form');
Route::post('/order-form/pdf', [OrderFormController::class, 'downloadPdf']) ->name('order.pdf');
Route::post('/order-review', [OrderFormController::class, 'review'])->name('order.review');
Route::post('/order/confirmation', [OrderFormController::class, 'confirm'])->name('order.confirmation');
