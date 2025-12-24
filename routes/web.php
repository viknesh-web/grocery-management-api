<?php

use App\Http\Controllers\API\V1\OrderFormController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;


Route::get('/', [OrderFormController::class, 'show']) ->name('order.form');
Route::post('/order-form/pdf', [OrderFormController::class, 'downloadPdf']) ->name('order.pdf');
Route::post('/order-review', [OrderFormController::class, 'review'])->name('order.review');
Route::post('/order/confirmation', [OrderFormController::class, 'confirm'])->name('order.confirmation');
Route::get('/order-review', [OrderFormController::class, 'showReview'])     ->name('order.review.show');

// Serve media files (category and product images)
Route::get('/media/{path}', function ($path) {
    $disk = Storage::disk('media');
    
    if (!$disk->exists($path)) {
        abort(404);
    }
    
    $file = $disk->get($path);
    $mimeType = $disk->mimeType($path);
    
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Content-Disposition', 'inline');
})->where('path', '.*');
