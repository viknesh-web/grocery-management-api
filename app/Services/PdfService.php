<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Services\CacheService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfService
{

    public function generatePriceList(array $productIds = [], string $pdfLayout = 'regular'): string
    {
        $cacheKey = CacheService::priceListKey($productIds, $pdfLayout);
        
        // Check if PDF is already cached
        $cachedPath = Cache::get($cacheKey);
        if ($cachedPath && Storage::disk('media')->exists($cachedPath)) {
            Log::info('Using cached price list PDF', ['path' => $cachedPath]);
            return $cachedPath;
        }

        // Generate new PDF
        $query = Product::enabled()->with('category');

        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->orderBy('category_id')->orderBy('name')->get();

        $viewName = ($pdfLayout === 'catalog') ? 'pdfs.catalog-price-list' : 'pdfs.generate';

        if ($pdfLayout === 'catalog') {
            $data = [
                'products' => $products,
                'generatedAt' => now(),
            ];
        } else {
            $data = [
                'products' => $products,
                'date' => now()->format('d M Y'),
                'time' => now()->format('h:i A'),
            ];
        }

        $pdf = Pdf::loadView($viewName, $data);
        $pdf->setPaper('A4', 'portrait');

        $disk = Storage::disk('media');

        $filename = 'price-list-' . now()->format('Y-m-d-H-i-s') . '.pdf';
        $path = 'pdfs/' . $filename;

        $directory = $disk->path('pdfs');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $disk->put($path, $pdf->output(), 'public');

        if (!$disk->exists($path)) {
            throw new BusinessException('Failed to generate PDF. Please try again.');
        }

        // Cache the path for 1 hour
        Cache::put($cacheKey, $path, CacheService::TTL_LONG);

        return $path;
    }

    public function getPdfUrl(string $pdfPath): string
    {
        return Storage::disk('media')->url($pdfPath);
    }

    public function getPdfPath(string $pdfPath): string
    {
        return Storage::disk('media')->path($pdfPath);
    }

    public function cleanupOldPdfs(int $days = 7): int
    {
        $deleted = 0;
        $files = Storage::disk('public')->files('pdfs');
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);
            if ($lastModified < $cutoffTime) {
                Storage::disk('public')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}

