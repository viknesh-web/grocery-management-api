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
 * This trait is designed for controllers that need to handle image uploads
 * in a consistent way. It delegates actual image operations to ImageService.
 * 
 * **Note**: With the Repository-Service-Controller pattern, image handling
 * is typically done in the service layer. This trait is kept for backward
 * compatibility and simple use cases.
 * 
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait HasImageUpload
{
    /**
     * Handle image upload for create/update operations.
     * 
     * Validates the uploaded file and delegates to ImageService for processing.
     * Returns the image path if upload is successful, null otherwise.
     * 
     * @param Request $request The HTTP request containing the image
     * @param ImageService $imageService The image service instance
     * @param string $imageFieldName The name of the image field in the request (default: 'image')
     * @param string $uploadMethod The method to call on ImageService (default: 'uploadProductImage')
     * @return string|null The uploaded image path relative to storage disk, or null if no image was uploaded
     * 
     * @example
     * ```php
     * $imagePath = $this->handleImageUpload($request, $imageService, 'image', 'uploadProductImage');
     * if ($imagePath) {
     *     $data['image'] = $imagePath;
     * }
     * ```
     */
    protected function handleImageUpload(
        Request $request,
        ImageService $imageService,
        string $imageFieldName = 'image',
        string $uploadMethod = 'uploadProductImage'
    ): ?string {
        // Check if file exists in request
        if (!$request->hasFile($imageFieldName)) {
            return null;
        }

        $image = $request->file($imageFieldName);

        // Validate file instance and validity
        if (!$image instanceof UploadedFile || !$image->isValid()) {
            return null;
        }

        // Validate that the method exists on ImageService
        if (!method_exists($imageService, $uploadMethod)) {
            throw new \BadMethodCallException(
                "Method {$uploadMethod} does not exist on " . get_class($imageService)
            );
        }

        // Call the appropriate upload method on ImageService
        return $imageService->{$uploadMethod}($image);
    }

    /**
     * Handle image upload with old image deletion.
     * 
     * Similar to handleImageUpload() but also handles deletion of old image
     * if a new image is being uploaded.
     * 
     * @param Request $request The HTTP request containing the image
     * @param ImageService $imageService The image service instance
     * @param string|null $oldImagePath The path of the old image to delete (if any)
     * @param string $imageFieldName The name of the image field in the request (default: 'image')
     * @param string $uploadMethod The method to call on ImageService (default: 'uploadProductImage')
     * @return string|null The uploaded image path relative to storage disk, or null if no image was uploaded
     * 
     * @example
     * ```php
     * $imagePath = $this->handleImageUploadWithDeletion(
     *     $request,
     *     $imageService,
     *     $product->image,
     *     'image',
     *     'uploadProductImage'
     * );
     * ```
     */
    protected function handleImageUploadWithDeletion(
        Request $request,
        ImageService $imageService,
        ?string $oldImagePath,
        string $imageFieldName = 'image',
        string $uploadMethod = 'uploadProductImage'
    ): ?string {
        // Check if file exists in request
        if (!$request->hasFile($imageFieldName)) {
            return null;
        }

        $image = $request->file($imageFieldName);

        // Validate file instance and validity
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
     * 
     * Checks for a boolean flag in the request that indicates the image
     * should be removed (typically used in update operations).
     * 
     * @param Request $request The HTTP request
     * @param string $flagFieldName The name of the removal flag field (default: 'image_removed')
     * @return bool Whether the image should be removed
     * 
     * @example
     * ```php
     * if ($this->shouldRemoveImage($request, 'image_removed')) {
     *     // Remove image logic
     * }
     * ```
     */
    protected function shouldRemoveImage(Request $request, string $flagFieldName = 'image_removed'): bool
    {
        return $request->boolean($flagFieldName, false);
    }

    /**
     * Remove image from model and delete file.
     * 
     * Removes the image path from the model and deletes the physical file
     * using ImageService. This is typically used when updating a model
     * and the user wants to remove the existing image.
     * 
     * @param \Illuminate\Database\Eloquent\Model $model The model instance
     * @param ImageService $imageService The image service instance
     * @param string $imageFieldName The name of the image field on the model (default: 'image')
     * @param string $deleteMethod The method to call on ImageService for deletion (default: 'deleteProductImage')
     * @return void
     * 
     * @example
     * ```php
     * $this->removeImageFromModel($product, $imageService, 'image', 'deleteProductImage');
     * ```
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
