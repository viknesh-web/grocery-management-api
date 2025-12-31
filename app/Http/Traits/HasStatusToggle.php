<?php

namespace App\Http\Traits;

/**
 * HasStatusToggle Trait
 * 
 * Provides common status toggle functionality for controllers.
 * 
 * @package App\Http\Traits
 */
trait HasStatusToggle
{
    /**
     * Toggle the status of a model.
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \App\Services\BaseService $service The service that handles the toggle operation
     * @param int $userId The ID of the user performing the action
     * @param string $statusField The name of the status field (default: 'status')
     * @return \Illuminate\Database\Eloquent\Model The updated model
     */
    protected function toggleModelStatus($model, $service, int $userId, string $statusField = 'status')
    {
        // Check if the service has a toggleStatus method
        if (method_exists($service, 'toggleStatus')) {
            return $service->toggleStatus($model, $userId);
        }

        // Fallback: manually toggle status
        $currentStatus = $model->{$statusField};
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        
        $model->{$statusField} = $newStatus;
        
        // Try to set updated_by if the field exists
        if (method_exists($model, 'getFillable') && in_array('updated_by', $model->getFillable())) {
            $model->updated_by = $userId;
        }
        
        $model->save();

        return $model;
    }
}


