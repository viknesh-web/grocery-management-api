<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Repositories\ProductRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class PdfService
{
   
    private const PDF_DIRECTORY = 'pdfs';
    private const DIRECTORY_PERMISSIONS = 0755;

    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function generatePriceList(array $productIds = [], string $pdfLayout = 'regular'): string
    {
        $cacheKey = CacheService::priceListKey($productIds, $pdfLayout);
        $cachedPath = Cache::get($cacheKey);
        
        if ($cachedPath && $this->fileExists($cachedPath)) {
            Log::info('Using cached price list PDF', ['path' => $cachedPath]);
            return $cachedPath;
        }

        $products = $this->getProductsForPdf($productIds);
        $viewData = $this->prepareViewData($products, $pdfLayout);
        $pdfPath = $this->generatePdfFile($viewData, $pdfLayout);
        if (!$this->fileExists($pdfPath)) {
            throw new BusinessException('Failed to generate PDF. Please try again.');
        }

        Cache::put($cacheKey, $pdfPath, CacheService::TTL_LONG);

        Log::info('Price list PDF generated', [
            'path' => $pdfPath,
            'layout' => $pdfLayout,
            'product_count' => $products->count(),
        ]);

        return $pdfPath;
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
        $disk = Storage::disk('media');
        $files = $disk->files(self::PDF_DIRECTORY);
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            if ($lastModified < $cutoffTime) {
                $disk->delete($file);
                $deleted++;
                
                Log::debug('Deleted old PDF file', [
                    'file' => $file,
                    'age_days' => round((now()->timestamp - $lastModified) / 86400, 1),
                ]);
            }
        }

        if ($deleted > 0) {
            Log::info('PDF cleanup completed', [
                'deleted_count' => $deleted,
                'days' => $days,
            ]);
        }

        return $deleted;
    }
 
    public function fileExists(string $pdfPath): bool
    {
        return Storage::disk('media')->exists($pdfPath);
    }

    public function deletePdf(string $pdfPath): bool
    {
        $disk = Storage::disk('media');
        
        if (!$disk->exists($pdfPath)) {
            return false;
        }

        $deleted = $disk->delete($pdfPath);
        
        if ($deleted) {
            Log::info('PDF file deleted', ['path' => $pdfPath]);
        }

        return $deleted;
    }
   
    protected function getProductsForPdf(array $productIds): \Illuminate\Database\Eloquent\Collection
    {
        $relations = ['category'];
        
        if (empty($productIds)) {
            return $this->productRepository->getActive($relations)
                ->sortBy('category_id')
                ->sortBy('name')
                ->values();
        }

        return $this->productRepository->findMany($productIds, $relations)
            ->sortBy('category_id')
            ->sortBy('name')
            ->values();
    }

    protected function prepareViewData(\Illuminate\Database\Eloquent\Collection $products, string $pdfLayout): array
    {
        if ($pdfLayout === 'catalog') {
            return [
                'products' => $products,
                'generatedAt' => now(),
            ];
        }

        return [
            'products' => $products,
            'date' => now()->format('d M Y'),
            'time' => now()->format('h:i A'),
        ];
    }
 
    protected function generatePdfFile(array $viewData, string $pdfLayout): string
    {
        $viewName = ($pdfLayout === 'catalog') ? 'pdfs.catalog-price-list' : 'pdfs.generate';

        $pdf = Pdf::loadView($viewName, $viewData);
        $pdf->setPaper('A4', 'portrait');
        $filename = $this->generateFilename($pdfLayout);
        $path = self::PDF_DIRECTORY . '/' . $filename;

        $this->ensureDirectoryExists();
        $disk = Storage::disk('media');
        $disk->put($path, $pdf->output(), 'public');

        return $path;
    }

    protected function generateFilename(string $pdfLayout): string
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $layoutSuffix = $pdfLayout === 'catalog' ? '-catalog' : '';
        return "price-list{$layoutSuffix}-{$timestamp}.pdf";
    }

    protected function ensureDirectoryExists(): void
    {
        $disk = Storage::disk('media');
        $directory = $disk->path(self::PDF_DIRECTORY);
        
        if (!is_dir($directory)) {
            mkdir($directory, self::DIRECTORY_PERMISSIONS, true);
        }
    }

    public function uploadCustomPdf(\Illuminate\Http\UploadedFile $file): string
    {
        $this->validatePdfFile($file);
        $filename = $this->sanitizeFilename($file->getClientOriginalName());
        $uniqueFilename = $this->generateUniqueFilename($filename);
        $this->ensureDirectoryExists();
        $path = $this->storePdfFile($file, $uniqueFilename);
        $pdfUrl = $this->getPdfUrl($path);

        Log::info('Custom PDF uploaded successfully', [
            'filename' => $uniqueFilename,
            'path' => $path,
            'size' => $file->getSize(),
        ]);

        return $pdfUrl;
    }

    protected function validatePdfFile(\Illuminate\Http\UploadedFile $file): void
    {
        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \App\Exceptions\ValidationException(
                'PDF file size exceeds maximum allowed size of 10MB',
                ['file' => ['The PDF file must not exceed 10MB']]
            );
        }

        // Validate MIME type
        $allowedMimeTypes = ['application/pdf'];
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \App\Exceptions\ValidationException(
                'Invalid file type. Only PDF files are allowed.',
                ['file' => ['Only PDF files are allowed']]
            );
        }

        // Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new \App\Exceptions\ValidationException(
                'Invalid file extension. Only PDF files are allowed.',
                ['file' => ['Only PDF files are allowed']]
            );
        }
    }

    protected function sanitizeFilename(string $originalName): string
    {
        // Remove path traversal attempts and null bytes
        $safeName = str_replace(['/', '\\', "\0", "\r", "\n"], '', $originalName);
        $safeName = trim($safeName);
        $safeName = preg_replace('/\s+/', '-', $safeName);
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $safeName);
        if (empty($safeName)) {
            $safeName = 'uploaded-pdf';
        }
        if (!pathinfo($safeName, PATHINFO_EXTENSION)) {
            $safeName .= '.pdf';
        }
        if (strlen($safeName) > 200) {
            $pathInfo = pathinfo($safeName);
            $nameWithoutExt = substr($pathInfo['filename'], 0, 200 - strlen($pathInfo['extension']) - 1);
            $safeName = $nameWithoutExt . '.' . $pathInfo['extension'];
        }

        return $safeName;
    }

    protected function generateUniqueFilename(string $baseFilename): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(4)); // 8 character random string
        $filename = $timestamp . '_' . $randomString . '_' . $baseFilename;
        $path = self::PDF_DIRECTORY . '/' . $filename;

        // Check if file already exists and generate unique name if needed
        $disk = Storage::disk('media');
        $counter = 1;
        $baseName = $filename;
        
        while ($disk->exists($path)) {
            $pathInfo = pathinfo($baseName);
            $nameWithoutExt = $pathInfo['filename'];
            $ext = $pathInfo['extension'] ?? 'pdf';
            $filename = $nameWithoutExt . '_' . $counter . '.' . $ext;
            $path = self::PDF_DIRECTORY . '/' . $filename;
            $counter++;
        }

        return $filename;
    }

    protected function storePdfFile(\Illuminate\Http\UploadedFile $file, string $filename): string
    {
        $disk = Storage::disk('media');
        $path = self::PDF_DIRECTORY . '/' . $filename;

        try {
            $stored = $disk->putFileAs(self::PDF_DIRECTORY, $file, $filename);
            
            if (!$stored) {
                throw new \App\Exceptions\BusinessException('Failed to store PDF file. Please try again.');
            }
            
            // Verify file was stored and is readable
            if (!$disk->exists($path)) {
                throw new \App\Exceptions\BusinessException('Failed to verify PDF file storage. Please try again.');
            }
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to store PDF file', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            
            throw new \App\Exceptions\BusinessException('Failed to upload PDF file. Please try again.');
        }
    }
}
