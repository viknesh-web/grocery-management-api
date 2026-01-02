<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

/**
 * Image Service
 * 
 * Handles all business logic for image operations.
 * 
 * Responsibilities:
 * - Image upload (products, categories)
 * - Image deletion
 * - Path generation
 * - URL generation
 * - Image processing (resizing)
 * - Error handling
 * 
 * All images are stored on the 'media' disk for consistency.
 */
class ImageService
{
    /**
     * Default image storage disk.
     */
    private const STORAGE_DISK = 'media';

    /**
     * Default image resize dimensions (maintains aspect ratio).
     */
    private const DEFAULT_MAX_WIDTH = 800;
    private const DEFAULT_MAX_HEIGHT = 800;

    /**
     * Directory permissions for image directories.
     */
    private const DIRECTORY_PERMISSIONS = 0755;

    /**
     * Image type directories.
     */
    private const DIRECTORY_PRODUCTS = 'products';
    private const DIRECTORY_CATEGORIES = 'category';

    protected ImageManager $imageManager;

    public function __construct()
    {
        // Create ImageManager with available driver
        $driver = extension_loaded('imagick') 
            ? new ImagickDriver() 
            : new GdDriver();
        
        $this->imageManager = new ImageManager($driver);
    }

    /**
     * Upload product image.
     * 
     * Handles:
     * - Old image deletion (if provided)
     * - Image processing (resizing)
     * - File storage
     * - Error handling with fallback
     *
     * @param UploadedFile $file
     * @param string|null $oldImagePath Old image path to delete
     * @return string New image path relative to storage disk
     * @throws BusinessException If upload fails
     */
    public function uploadProductImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        return $this->uploadImage($file, self::DIRECTORY_PRODUCTS, $oldImagePath);
    }

    /**
     * Upload category image.
     * 
     * Handles:
     * - Old image deletion (if provided)
     * - Image processing (resizing)
     * - File storage
     * - Error handling with fallback
     *
     * @param UploadedFile $file
     * @param string|null $oldImagePath Old image path to delete
     * @return string New image path relative to storage disk
     * @throws BusinessException If upload fails
     */
    public function uploadCategoryImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        return $this->uploadImage($file, self::DIRECTORY_CATEGORIES, $oldImagePath);
    }

    /**
     * Delete product image.
     * 
     * Handles:
     * - Path normalization
     * - File deletion
     * - Error handling
     *
     * @param string|null $imagePath Image path to delete
     * @return bool True if deleted, false otherwise
     */
    public function deleteProductImage(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        return $this->deleteImage($imagePath, self::DIRECTORY_PRODUCTS);
    }

    /**
     * Delete category image.
     * 
     * Handles:
     * - Path normalization
     * - File deletion
     * - Error handling
     *
     * @param string|null $imagePath Image path to delete
     * @return bool True if deleted, false otherwise
     */
    public function deleteCategoryImage(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        return $this->deleteImage($imagePath, self::DIRECTORY_CATEGORIES);
    }

    /**
     * Get image URL.
     * 
     * Business logic: Generates public URL for image file.
     *
     * @param string|null $imagePath Image path relative to storage disk
     * @return string|null Public URL or null if path is empty
     */
    public function getImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // Normalize path (remove leading slash if present)
        $normalizedPath = ltrim($imagePath, '/');

        return Storage::disk(self::STORAGE_DISK)->url($normalizedPath);
    }

    /**
     * Check if image file exists.
     * 
     * Business logic: Validates file existence.
     *
     * @param string|null $imagePath Image path relative to storage disk
     * @return bool
     */
    public function imageExists(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        $normalizedPath = $this->normalizePath($imagePath);
        return Storage::disk(self::STORAGE_DISK)->exists($normalizedPath);
    }

    /**
     * Upload image file.
     * 
     * Business logic: Common upload logic for all image types.
     *
     * @param UploadedFile $file
     * @param string $directory Directory name (products, category, etc.)
     * @param string|null $oldImagePath Old image path to delete
     * @return string New image path relative to storage disk
     * @throws BusinessException If upload fails
     */
    protected function uploadImage(UploadedFile $file, string $directory, ?string $oldImagePath = null): string
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        // Delete old image if provided (business logic - cleanup)
        if (!empty($oldImagePath)) {
            $this->deleteImage($oldImagePath, $directory);
        }

        // Generate filename (business logic - file naming)
        $filename = $this->generateFilename($file);
        $path = $directory . '/' . $filename;

        // Ensure directory exists (business logic - directory management)
        $this->ensureDirectoryExists($directory);

        try {
            // Process and upload image (business logic - image processing)
            $this->processAndStoreImage($file, $path, $disk);

            // Verify file was stored (business logic - validation)
            if (!$disk->exists($path)) {
                throw new BusinessException('Failed to upload image. Please try again.');
            }

            Log::info('Image uploaded successfully', [
                'path' => $path,
                'directory' => $directory,
                'size' => $file->getSize(),
            ]);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Image processing failed, attempting fallback upload', [
                'error' => $e->getMessage(),
                'path' => $path,
                'directory' => $directory,
            ]);

            // Fallback: upload without processing (business logic - graceful degradation)
            try {
                $disk->putFileAs($directory, $file, $filename, 'public');

                if (!$disk->exists($path)) {
                    throw new BusinessException('Failed to upload image. Please try again.');
                }

                Log::info('Image uploaded via fallback method', [
                    'path' => $path,
                    'directory' => $directory,
                ]);

                return $path;
            } catch (\Throwable $fallbackError) {
                Log::error('Image upload failed completely', [
                    'error' => $fallbackError->getMessage(),
                    'path' => $path,
                    'directory' => $directory,
                    'trace' => $fallbackError->getTraceAsString(),
                ]);

                throw new BusinessException('Failed to upload image. Please try again.');
            }
        }
    }

    /**
     * Delete image file.
     * 
     * Business logic: Common deletion logic for all image types.
     *
     * @param string $imagePath Image path (may or may not include directory prefix)
     * @param string $directory Expected directory name
     * @return bool True if deleted, false otherwise
     */
    protected function deleteImage(string $imagePath, string $directory): bool
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        
        // Normalize path (business logic - path handling)
        $normalizedPath = $this->normalizePath($imagePath, $directory);

        if (!$disk->exists($normalizedPath)) {
            Log::debug('Image file does not exist, skipping deletion', [
                'path' => $normalizedPath,
                'original_path' => $imagePath,
            ]);
            return false;
        }

        $deleted = $disk->delete($normalizedPath);

        if ($deleted) {
            Log::info('Image file deleted', [
                'path' => $normalizedPath,
                'directory' => $directory,
            ]);
        } else {
            Log::warning('Failed to delete image file', [
                'path' => $normalizedPath,
                'directory' => $directory,
            ]);
        }

        return $deleted;
    }

    /**
     * Process and store image.
     * 
     * Business logic: Resizes image and stores it.
     *
     * @param UploadedFile $file
     * @param string $path Target path
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk
     * @return void
     * @throws \Exception
     */
    protected function processAndStoreImage(UploadedFile $file, string $path, $disk): void
    {
        $image = $this->imageManager
            ->read($file->getRealPath())
            ->resize(self::DEFAULT_MAX_WIDTH, self::DEFAULT_MAX_HEIGHT, fn ($c) => $c->aspectRatio());

        $disk->put($path, (string) $image->encode(), 'public');
    }

    /**
     * Generate unique filename for uploaded file.
     * 
     * Business logic: Creates unique filename to prevent conflicts.
     *
     * @param UploadedFile $file
     * @return string Filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Ensure directory exists.
     * 
     * Business logic: Creates directory if it doesn't exist.
     *
     * @param string $directory Directory name
     * @return void
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $directoryPath = $disk->path($directory);
        
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, self::DIRECTORY_PERMISSIONS, true);
        }
    }

    /**
     * Normalize image path.
     * 
     * Business logic: Handles various path formats and ensures correct directory prefix.
     *
     * @param string $imagePath Image path (may or may not include directory prefix)
     * @param string|null $directory Expected directory name (for normalization)
     * @return string Normalized path
     */
    protected function normalizePath(string $imagePath, ?string $directory = null): string
    {
        // Remove leading slash
        $normalized = ltrim($imagePath, '/');

        // If directory is provided, ensure path has correct prefix
        if ($directory !== null) {
            // Remove existing directory prefix if present
            $normalized = preg_replace('#^' . preg_quote($directory, '#') . '/#i', '', $normalized);
            // Add correct directory prefix
            $normalized = $directory . '/' . $normalized;
        }

        return $normalized;
    }
}
