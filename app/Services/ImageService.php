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

    public function uploadProductImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        return $this->uploadImage($file, self::DIRECTORY_PRODUCTS, $oldImagePath);
    }

    public function uploadCategoryImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        return $this->uploadImage($file, self::DIRECTORY_CATEGORIES, $oldImagePath);
    }
  
    public function deleteProductImage(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        return $this->deleteImage($imagePath, self::DIRECTORY_PRODUCTS);
    }

    public function deleteCategoryImage(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        return $this->deleteImage($imagePath, self::DIRECTORY_CATEGORIES);
    }

    public function getImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        $normalizedPath = ltrim($imagePath, '/');

        return Storage::disk(self::STORAGE_DISK)->url($normalizedPath);
    }

    public function imageExists(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return false;
        }

        $normalizedPath = $this->normalizePath($imagePath);
        return Storage::disk(self::STORAGE_DISK)->exists($normalizedPath);
    }

    protected function uploadImage(UploadedFile $file, string $directory, ?string $oldImagePath = null): string
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        if (!empty($oldImagePath)) {
            $this->deleteImage($oldImagePath, $directory);
        }

        $filename = $this->generateFilename($file);
        $path = $directory . '/' . $filename;

        $this->ensureDirectoryExists($directory);

        try {
            $this->processAndStoreImage($file, $path, $disk);
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

    protected function processAndStoreImage(UploadedFile $file, string $path, $disk): void
    {
        $image = $this->imageManager
            ->read($file->getRealPath())
            ->resize(self::DEFAULT_MAX_WIDTH, self::DEFAULT_MAX_HEIGHT, fn ($c) => $c->aspectRatio());

        $disk->put($path, (string) $image->encode(), 'public');
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return uniqid() . '_' . time() . '.' . $extension;
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $directoryPath = $disk->path($directory);
        
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, self::DIRECTORY_PERMISSIONS, true);
        }
    }

    protected function normalizePath(string $imagePath, ?string $directory = null): string
    {
        $normalized = ltrim($imagePath, '/');

        if ($directory !== null) {
            $normalized = preg_replace('#^' . preg_quote($directory, '#') . '/#i', '', $normalized);          
            $normalized = $directory . '/' . $normalized;
        }

        return $normalized;
    }
}
