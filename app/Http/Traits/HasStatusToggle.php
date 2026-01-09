<?php

namespace App\Http\Traits;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/**
 * HasStatusToggle Trait
 * 
 * Provides common status toggle functionality for controllers.
 * @package App\Http\Traits
 * @since 1.0.0
 */
trait HasStatusToggle
{
    /**
     * Toggle the status of a model.
     * 
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
     */
    protected function isModelActive(Model $model, string $statusField = 'status'): bool
    {
        return isset($model->{$statusField}) && $model->{$statusField} === 'active';
    }

    /**
     * Check if a model is inactive.    
     */
    protected function isModelInactive(Model $model, string $statusField = 'status'): bool
    {
        return isset($model->{$statusField}) && $model->{$statusField} === 'inactive';
    }
}
