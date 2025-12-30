<?php

namespace App\Http\Traits;

use Illuminate\Http\UploadedFile;

/**
 * HasImageUpload Trait
 * 
 * Provides common image upload handling functionality for controllers.
 * 
 * @package App\Http\Traits
 */
trait HasImageUpload
{
    /**
     * Handle image upload for create/update operations.
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Services\ImageService $imageService
     * @param string $imageFieldName The name of the image field in the request (default: 'image')
     * @param string $uploadMethod The method to call on ImageService (default: 'uploadProductImage')
     * @return string|null The uploaded image path or null if no image was uploaded
     */
    protected function handleImageUpload(
        $request,
        $imageService,
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

        // Call the appropriate upload method on ImageService
        return $imageService->{$uploadMethod}($image);
    }

    /**
     * Handle image removal flag.
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $flagFieldName The name of the removal flag field (default: 'image_removed')
     * @return bool Whether the image should be removed
     */
    protected function shouldRemoveImage($request, string $flagFieldName = 'image_removed'): bool
    {
        return $request->boolean($flagFieldName, false);
    }

    /**
     * Handle image removal from model.
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \App\Services\ImageService $imageService
     * @param string $imageFieldName The name of the image field on the model (default: 'image')
     * @param string $deleteMethod The method to call on ImageService for deletion (default: 'deleteProductImage')
     * @return void
     */
    protected function removeImageFromModel($model, $imageService, string $imageFieldName = 'image', string $deleteMethod = 'deleteProductImage'): void
    {
        if ($model->{$imageFieldName}) {
            // Call the appropriate delete method (deleteProductImage, deleteCategoryImage, etc.)
            if (method_exists($imageService, $deleteMethod)) {
                $imageService->{$deleteMethod}($model->{$imageFieldName});
            }
            $model->{$imageFieldName} = null;
        }
    }
}

