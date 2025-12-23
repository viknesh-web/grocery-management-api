<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class ImageService
{
    protected ImageManager $image;

    public function __construct()
    {
        // Create ImageManager with available driver
        $driver = extension_loaded('imagick') 
            ? new ImagickDriver() 
            : new GdDriver();
        
        $this->image = new ImageManager($driver);
    }

    public function uploadProductImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        // Use the `media` disk so product images are stored alongside category images
        $disk = Storage::disk('media');

        if ($oldImagePath && $disk->exists($oldImagePath)) {
            $disk->delete($oldImagePath);
        }

        $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = 'products/' . $filename;

        // Ensure the products directory exists
        $directory = $disk->path('products');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            // Read from uploaded file directly (not from storage)
            $image = $this->image
                ->read($file->getRealPath())
                ->resize(800, 800, fn ($c) => $c->aspectRatio());

            // Store the processed image
            $disk->put($path, (string) $image->encode(), 'public');

            // Verify the file was stored
            if (!$disk->exists($path)) {
                throw new \RuntimeException('Failed to store product image');
            }
        } catch (\Throwable $e) {
            // If resize/encode fails, fallback to storing original file
            report($e);
            try {
                $disk->putFileAs('products', $file, $filename, 'public');
                
                // Verify the file was stored
                if (!$disk->exists($path)) {
                    throw new \RuntimeException('Failed to store product image');
                }
            } catch (\Throwable $fallbackError) {
                report($fallbackError);
                throw new \RuntimeException('Failed to store product image: ' . $fallbackError->getMessage());
            }
        }

        // Final verification
        if (!$disk->exists($path)) {
            throw new \RuntimeException('Product image was not saved correctly');
        }

        return $path;
    }

    public function uploadCategoryImage(UploadedFile $file, ?string $oldImagePath = null): string
    {
        $media = Storage::disk('media');

        if ($oldImagePath && $media->exists('category/' . $oldImagePath)) {
            $media->delete('category/' . $oldImagePath);
        }

        $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = 'category/' . $filename;

        // Ensure the category directory exists
        $directory = $media->path('category');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            // Read from uploaded file directly (not from storage)
            $image = $this->image
                ->read($file->getRealPath())
                ->resize(800, 800, fn ($c) => $c->aspectRatio());

            // Store the processed image
            $media->put($path, (string) $image->encode(), 'public');

            // Verify the file was stored
            if (!$media->exists($path)) {
                throw new \RuntimeException('Failed to store category image');
            }
        } catch (\Throwable $e) {
            // If resize/encode fails, fallback to storing original file
            report($e);
            try {
                $media->putFileAs('category', $file, $filename, 'public');

                // Verify the file was stored
                if (!$media->exists($path)) {
                    throw new \RuntimeException('Failed to store category image');
                }
            } catch (\Throwable $fallbackError) {
                report($fallbackError);
                throw new \RuntimeException('Failed to store category image: ' . $fallbackError->getMessage());
            }
        }

        // Final verification
        if (!$media->exists($path)) {
            throw new \RuntimeException('Category image was not saved correctly');
        }

        return $filename;
    }

    public function deleteProductImage(string $imagePath): bool
    {
        if (!$imagePath) {
            return false;
        }
        return Storage::disk('public')->exists($imagePath)
            ? Storage::disk('public')->delete($imagePath)
            : false;
    }

    public function deleteCategoryImage(string $imagePath): bool
    {
        if (!$imagePath) {
            return false;
        }
        $path = 'category/' . ltrim($imagePath, '/');
        return Storage::disk('media')->exists($path)
            ? Storage::disk('media')->delete($path)
            : false;
    }

    public function getImageUrl(?string $imagePath): ?string
    {
        return $imagePath
            ? asset('storage/' . $imagePath)
            : null;
    }
}

