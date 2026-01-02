<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Repositories\ProductRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PDF Service
 * 
 * Handles all business logic for PDF generation operations.
 * 
 * Responsibilities:
 * - PDF generation logic
 * - Cache management (via CacheService)
 * - File storage coordination
 * - URL generation
 * - Cleanup operations
 * 
 * Does NOT contain:
 * - Direct Product model queries (uses ProductRepository)
 * - Direct cache operations (uses CacheService)
 */
class PdfService
{
    /**
     * Default PDF storage directory.
     */
    private const PDF_DIRECTORY = 'pdfs';

    /**
     * Default PDF file permissions.
     */
    private const DIRECTORY_PERMISSIONS = 0755;

    public function __construct(
        private ProductRepository $productRepository
    ) {}

    /**
     * Generate price list PDF.
     * 
     * Handles:
     * - Cache checking (via CacheService)
     * - Product retrieval (via ProductRepository)
     * - PDF generation (via DomPDF)
     * - File storage
     * - Cache storage
     *
     * @param array $productIds Product IDs to include (empty = all enabled products)
     * @param string $pdfLayout PDF layout ('regular' or 'catalog')
     * @return string PDF file path relative to storage disk
     * @throws BusinessException If PDF generation fails
     */
    public function generatePriceList(array $productIds = [], string $pdfLayout = 'regular'): string
    {
        // Check cache first (business logic - cache management)
        $cacheKey = CacheService::priceListKey($productIds, $pdfLayout);
        $cachedPath = Cache::get($cacheKey);
        
        if ($cachedPath && $this->fileExists($cachedPath)) {
            Log::info('Using cached price list PDF', ['path' => $cachedPath]);
            return $cachedPath;
        }

        // Get products for PDF (business logic - data retrieval via repository)
        $products = $this->getProductsForPdf($productIds);

        // Prepare view data (business logic - data preparation)
        $viewData = $this->prepareViewData($products, $pdfLayout);

        // Generate PDF (business logic - PDF generation)
        $pdfPath = $this->generatePdfFile($viewData, $pdfLayout);

        // Verify PDF was created (business logic - validation)
        if (!$this->fileExists($pdfPath)) {
            throw new BusinessException('Failed to generate PDF. Please try again.');
        }

        // Cache the path (business logic - cache management)
        Cache::put($cacheKey, $pdfPath, CacheService::TTL_LONG);

        Log::info('Price list PDF generated', [
            'path' => $pdfPath,
            'layout' => $pdfLayout,
            'product_count' => $products->count(),
        ]);

        return $pdfPath;
    }

    /**
     * Get PDF URL for a given path.
     * 
     * Business logic: Generates public URL for PDF file.
     *
     * @param string $pdfPath PDF file path relative to storage disk
     * @return string Public URL for the PDF
     */
    public function getPdfUrl(string $pdfPath): string
    {
        return Storage::disk('media')->url($pdfPath);
    }

    /**
     * Get absolute file path for a PDF.
     * 
     * Business logic: Returns filesystem path for PDF file.
     *
     * @param string $pdfPath PDF file path relative to storage disk
     * @return string Absolute filesystem path
     */
    public function getPdfPath(string $pdfPath): string
    {
        return Storage::disk('media')->path($pdfPath);
    }

    /**
     * Cleanup old PDF files.
     * 
     * Business logic: Removes PDF files older than specified days.
     *
     * @param int $days Number of days to keep PDFs (default: 7)
     * @return int Number of files deleted
     */
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

    /**
     * Check if PDF file exists.
     * 
     * Business logic: Validates file existence.
     *
     * @param string $pdfPath PDF file path relative to storage disk
     * @return bool
     */
    public function fileExists(string $pdfPath): bool
    {
        return Storage::disk('media')->exists($pdfPath);
    }

    /**
     * Delete a PDF file.
     * 
     * Business logic: Removes PDF file from storage.
     *
     * @param string $pdfPath PDF file path relative to storage disk
     * @return bool True if file was deleted, false otherwise
     */
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

    /**
     * Get products for PDF generation.
     * 
     * Business logic: Retrieves products via repository with proper filtering.
     *
     * @param array $productIds Product IDs to include (empty = all enabled products)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getProductsForPdf(array $productIds): \Illuminate\Database\Eloquent\Collection
    {
        $relations = ['category'];
        
        if (empty($productIds)) {
            // Get all active products (business logic - use repository)
            // Note: 'enabled' scope filters by status = 'active'
            return $this->productRepository->getActive($relations)
                ->sortBy('category_id')
                ->sortBy('name')
                ->values();
        }

        // Get specific products by IDs (business logic - use repository)
        return $this->productRepository->findMany($productIds, $relations)
            ->sortBy('category_id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Prepare view data for PDF generation.
     * 
     * Business logic: Formats data for PDF view templates.
     *
     * @param \Illuminate\Database\Eloquent\Collection $products
     * @param string $pdfLayout PDF layout ('regular' or 'catalog')
     * @return array View data
     */
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

    /**
     * Generate PDF file from view data.
     * 
     * Business logic: Creates PDF file and stores it.
     *
     * @param array $viewData Data for PDF view
     * @param string $pdfLayout PDF layout ('regular' or 'catalog')
     * @return string PDF file path relative to storage disk
     * @throws BusinessException If PDF generation fails
     */
    protected function generatePdfFile(array $viewData, string $pdfLayout): string
    {
        // Determine view name (business logic - view selection)
        $viewName = ($pdfLayout === 'catalog') ? 'pdfs.catalog-price-list' : 'pdfs.generate';

        // Generate PDF (delegated to DomPDF)
        $pdf = Pdf::loadView($viewName, $viewData);
        $pdf->setPaper('A4', 'portrait');

        // Generate filename (business logic - file naming)
        $filename = $this->generateFilename($pdfLayout);
        $path = self::PDF_DIRECTORY . '/' . $filename;

        // Ensure directory exists (business logic - directory management)
        $this->ensureDirectoryExists();

        // Store PDF file (business logic - file storage)
        $disk = Storage::disk('media');
        $disk->put($path, $pdf->output(), 'public');

        return $path;
    }

    /**
     * Generate filename for PDF.
     * 
     * Business logic: Creates unique filename based on layout and timestamp.
     *
     * @param string $pdfLayout PDF layout ('regular' or 'catalog')
     * @return string Filename
     */
    protected function generateFilename(string $pdfLayout): string
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $layoutSuffix = $pdfLayout === 'catalog' ? '-catalog' : '';
        return "price-list{$layoutSuffix}-{$timestamp}.pdf";
    }

    /**
     * Ensure PDF directory exists.
     * 
     * Business logic: Creates directory if it doesn't exist.
     *
     * @return void
     */
    protected function ensureDirectoryExists(): void
    {
        $disk = Storage::disk('media');
        $directory = $disk->path(self::PDF_DIRECTORY);
        
        if (!is_dir($directory)) {
            mkdir($directory, self::DIRECTORY_PERMISSIONS, true);
        }
    }

    /**
     * Upload and store a custom PDF file securely.
     * 
     * Handles:
     * - File validation
     * - Filename sanitization
     * - File storage
     * - URL generation
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string PDF URL
     * @throws \App\Exceptions\ValidationException
     * @throws \App\Exceptions\BusinessException
     */
    public function uploadCustomPdf(\Illuminate\Http\UploadedFile $file): string
    {
        // Validate file (business logic - validation)
        $this->validatePdfFile($file);

        // Sanitize filename (business logic - filename handling)
        $filename = $this->sanitizeFilename($file->getClientOriginalName());

        // Generate unique filename (business logic - file naming)
        $uniqueFilename = $this->generateUniqueFilename($filename);

        // Ensure directory exists (business logic - directory management)
        $this->ensureDirectoryExists();

        // Store file (business logic - file storage)
        $path = $this->storePdfFile($file, $uniqueFilename);

        // Generate URL (business logic - URL generation)
        $pdfUrl = $this->getPdfUrl($path);

        Log::info('Custom PDF uploaded successfully', [
            'filename' => $uniqueFilename,
            'path' => $path,
            'size' => $file->getSize(),
        ]);

        return $pdfUrl;
    }

    /**
     * Validate PDF file.
     * 
     * Business logic: Ensures file meets requirements.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     * @throws \App\Exceptions\ValidationException
     */
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

    /**
     * Sanitize filename.
     * 
     * Business logic: Removes dangerous characters and normalizes filename.
     *
     * @param string $originalName
     * @return string
     */
    protected function sanitizeFilename(string $originalName): string
    {
        // Remove path traversal attempts and null bytes
        $safeName = str_replace(['/', '\\', "\0", "\r", "\n"], '', $originalName);
        
        // Trim and normalize whitespace
        $safeName = trim($safeName);
        
        // Replace spaces with hyphens
        $safeName = preg_replace('/\s+/', '-', $safeName);
        
        // Remove all characters except alphanumeric, hyphens, underscores, and dots
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $safeName);
        
        // Ensure filename is not empty
        if (empty($safeName)) {
            $safeName = 'uploaded-pdf';
        }
        
        // Ensure .pdf extension
        if (!pathinfo($safeName, PATHINFO_EXTENSION)) {
            $safeName .= '.pdf';
        }
        
        // Limit filename length (max 200 characters for base name)
        if (strlen($safeName) > 200) {
            $pathInfo = pathinfo($safeName);
            $nameWithoutExt = substr($pathInfo['filename'], 0, 200 - strlen($pathInfo['extension']) - 1);
            $safeName = $nameWithoutExt . '.' . $pathInfo['extension'];
        }

        return $safeName;
    }

    /**
     * Generate unique filename.
     * 
     * Business logic: Creates unique filename to prevent conflicts.
     *
     * @param string $baseFilename
     * @return string
     */
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

    /**
     * Store PDF file.
     * 
     * Business logic: Stores file to disk and verifies storage.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $filename
     * @return string File path relative to storage disk
     * @throws \App\Exceptions\BusinessException
     */
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
