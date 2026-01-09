<?php

namespace App\Http\Traits;

use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;

/**
 * HasImageUpload Trait
 * 
 * Provides common image upload handling functionality for controllers.
 * 
 * 
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait HasImageUpload
{  
    protected function handleImageUpload(
        Request $request,
        ImageService $imageService,
        string $imageFieldName = 'image',
        string $uploadMethod = 'uploadProductImage'
    ): ?string {        
        if (!$request->hasFile($imageFieldName)) {
            return null;
        }

        $image = $request->file($imageFieldName);

        if (!$image instanceof UploadedFile || !$image->isValid()) {
            return null;
        }

        if (!method_exists($imageService, $uploadMethod)) {
            throw new \BadMethodCallException(
                "Method {$uploadMethod} does not exist on " . get_class($imageService)
            );
        }

        return $imageService->{$uploadMethod}($image);
    }

    /**
     * Handle image upload with old image deletion.
     */
    protected function handleImageUploadWithDeletion(
        Request $request,
        ImageService $imageService,
        ?string $oldImagePath,
        string $imageFieldName = 'image',
        string $uploadMethod = 'uploadProductImage'
    ): ?string {
        if (!$request->hasFile($imageFieldName)) {
            return null;
        }

        $image = $request->file($imageFieldName);

        if (!$image instanceof UploadedFile || !$image->isValid()) {
            return null;
        }

        // Validate that the method exists on ImageService
        if (!method_exists($imageService, $uploadMethod)) {
            throw new \BadMethodCallException(
                "Method {$uploadMethod} does not exist on " . get_class($imageService)
            );
        }

        // Call the appropriate upload method on ImageService with old image path
        return $imageService->{$uploadMethod}($image, $oldImagePath);
    }

    /**
     * Check if image should be removed based on request flag.    
     */
    protected function shouldRemoveImage(Request $request, string $flagFieldName = 'image_removed'): bool
    {
        return $request->boolean($flagFieldName, false);
    }

    /**
     * Remove image from model and delete file.
     */
    protected function removeImageFromModel(
        \Illuminate\Database\Eloquent\Model $model,
        ImageService $imageService,
        string $imageFieldName = 'image',
        string $deleteMethod = 'deleteProductImage'
    ): void {
        if (empty($model->{$imageFieldName})) {
            return;
        }

        $imagePath = $model->{$imageFieldName};

        // Validate that the delete method exists on ImageService
        if (!method_exists($imageService, $deleteMethod)) {
            throw new \BadMethodCallException(
                "Method {$deleteMethod} does not exist on " . get_class($imageService)
            );
        }

        // Delete the image file using ImageService
        $imageService->{$deleteMethod}($imagePath);

        // Clear the image field on the model
        $model->{$imageFieldName} = null;
    }
}
