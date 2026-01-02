<?php

namespace App\Http\Traits;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * HasStatusToggle Trait
 * 
 * Provides common status toggle functionality for controllers.
 * 
 * This trait is designed for controllers that need to toggle model status
 * in a consistent way. It delegates actual status operations to services
 * that extend BaseService.
 * 
 * **Note**: With the Repository-Service-Controller pattern, status toggling
 * is typically done in the service layer. This trait is kept for backward
 * compatibility and simple use cases.
 * 
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait HasStatusToggle
{
    /**
     * Toggle the status of a model.
     * 
     * Toggles the model's status between 'active' and 'inactive'.
     * Prefers using the service's toggleStatus() method if available,
     * otherwise falls back to manual status toggling.
     * 
     * @param Model $model The model instance to toggle
     * @param BaseService|null $service The service that handles the toggle operation (optional)
     * @param int|null $userId The ID of the user performing the action (optional)
     * @param string $statusField The name of the status field (default: 'status')
     * @return Model The updated model instance
     * @throws \InvalidArgumentException If model doesn't have the status field
     * 
     * @example
     * ```php
     * $product = Product::findOrFail($id);
     * $updatedProduct = $this->toggleModelStatus($product, $productService, $userId);
     * ```
     */
    protected function toggleModelStatus(
        Model $model,
        ?BaseService $service = null,
        ?int $userId = null,
        string $statusField = 'status'
    ): Model {
        // Validate that the model has the status field
        if (!isset($model->{$statusField})) {
            throw new \InvalidArgumentException(
                "Model " . get_class($model) . " does not have a '{$statusField}' field."
            );
        }

        // Prefer service method if available
        if ($service !== null && method_exists($service, 'toggleStatus')) {
            // Use call_user_func to avoid static analysis errors
            // The method exists at runtime (verified by method_exists above)
            return call_user_func([$service, 'toggleStatus'], $model, $userId);
        }

        // Fallback: manually toggle status
        $currentStatus = $model->{$statusField};
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        
        $model->{$statusField} = $newStatus;
        
        // Try to set updated_by if the field exists and userId is provided
        if ($userId !== null && $model->isFillable('updated_by')) {
            $model->updated_by = $userId;
        }
        
        $model->save();

        return $model;
    }

    /**
     * Check if a model is active.
     * 
     * Helper method to check if a model's status is 'active'.
     * 
     * @param Model $model The model instance to check
     * @param string $statusField The name of the status field (default: 'status')
     * @return bool True if model is active, false otherwise
     * 
     * @example
     * ```php
     * if ($this->isModelActive($product)) {
     *     // Product is active
     * }
     * ```
     */
    protected function isModelActive(Model $model, string $statusField = 'status'): bool
    {
        return isset($model->{$statusField}) && $model->{$statusField} === 'active';
    }

    /**
     * Check if a model is inactive.
     * 
     * Helper method to check if a model's status is 'inactive'.
     * 
     * @param Model $model The model instance to check
     * @param string $statusField The name of the status field (default: 'status')
     * @return bool True if model is inactive, false otherwise
     * 
     * @example
     * ```php
     * if ($this->isModelInactive($product)) {
     *     // Product is inactive
     * }
     * ```
     */
    protected function isModelInactive(Model $model, string $statusField = 'status'): bool
    {
        return isset($model->{$statusField}) && $model->{$statusField} === 'inactive';
    }
}
