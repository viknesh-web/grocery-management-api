<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up old PDF files daily
Schedule::call(function () {
    $pdfService = app(\App\Services\PdfService::class);
    $deleted = $pdfService->cleanupOldPdfs(7); // 7 days
    \Log::info("Cleaned up {$deleted} old PDF files");
})->daily();

// Clear cache every 6 hours to prevent stale data
Schedule::command('cache:clear-app')->everySixHours();
