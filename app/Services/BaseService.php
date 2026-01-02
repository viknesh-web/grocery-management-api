<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base Service
 * 
 * Abstract base class providing common service patterns:
 * - Transaction handling
 * - Error handling
 * - Cache management
 * - Common CRUD operations
 * 
 * All services should extend this class for consistency.
 * 
 * @method mixed toggleStatus(\Illuminate\Database\Eloquent\Model $model, int $userId) Toggle model status (implemented in child classes)
 */
abstract class BaseService
{
    /**
     * Execute a callback within a database transaction.
     * 
     * Automatically handles commit/rollback and error logging.
     *
     * @param callable $callback
     * @param string|null $errorMessage Custom error message for logging
     * @return mixed
     * @throws \Exception
     */
    protected function transaction(callable $callback, ?string $errorMessage = null): mixed
    {
        DB::beginTransaction();
        
        try {
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log error with context
            $message = $errorMessage ?? 'Transaction failed in ' . static::class;
            Log::error($message, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute a callback with error handling and logging.
     * 
     * Use this for operations that don't require transactions.
     *
     * @param callable $callback
     * @param string|null $errorMessage Custom error message for logging
     * @return mixed
     * @throws \Exception
     */
    protected function handle(callable $callback, ?string $errorMessage = null): mixed
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            $message = $errorMessage ?? 'Operation failed in ' . static::class;
            Log::error($message, [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Clear cache for a model.
     * 
     * Override in child classes to implement specific cache clearing logic.
     *
     * @param Model|null $model
     * @param int|null $id
     * @return void
     */
    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        // Override in child classes to implement specific cache clearing
    }

    /**
     * Clear all caches for a model type.
     * 
     * Override in child classes to implement specific cache clearing logic.
     *
     * @return void
     */
    protected function clearAllModelCache(): void
    {
        // Override in child classes to implement specific cache clearing
    }

    /**
     * Prepare data for creation/update.
     * 
     * Override in child classes to add default values, transformations, etc.
     *
     * @param array $data
     * @param int|null $userId Current user ID
     * @return array
     */
    protected function prepareData(array $data, ?int $userId = null): array
    {
        // Add timestamps and user tracking if not present
        if ($userId !== null) {
            if (!isset($data['created_by']) && !isset($data['id'])) {
                $data['created_by'] = $userId;
            }
            if (!isset($data['updated_by'])) {
                $data['updated_by'] = $userId;
            }
        }
        
        return $data;
    }

    /**
     * Validate business rules before operation.
     * 
     * Override in child classes to implement specific business rule validation.
     *
     * @param array $data
     * @param Model|null $model Existing model (for updates)
     * @return void
     * @throws \App\Exceptions\ValidationException
     */
    protected function validateBusinessRules(array $data, ?Model $model = null): void
    {
        // Override in child classes to implement business rule validation
    }

    /**
     * Perform actions after model creation.
     * 
     * Override in child classes to implement post-creation logic.
     *
     * @param Model $model
     * @param array $data Original data
     * @return void
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Override in child classes to implement post-creation logic
        // Example: Create related records, send notifications, etc.
    }

    /**
     * Perform actions after model update.
     * 
     * Override in child classes to implement post-update logic.
     *
     * @param Model $model
     * @param array $data Original data
     * @param array $oldData Previous model data
     * @return void
     */
    protected function afterUpdate(Model $model, array $data, array $oldData): void
    {
        // Override in child classes to implement post-update logic
        // Example: Create audit logs, send notifications, etc.
    }

    /**
     * Perform actions after model deletion.
     * 
     * Override in child classes to implement post-deletion logic.
     *
     * @param Model $model
     * @return void
     */
    protected function afterDelete(Model $model): void
    {
        // Override in child classes to implement post-deletion logic
        // Example: Clean up related files, send notifications, etc.
    }

    /**
     * Get old model data for comparison.
     * 
     * Extracts relevant attributes from a model for comparison.
     *
     * @param Model $model
     * @param array $fields Fields to extract (empty array = all fillable)
     * @return array
     */
    protected function getOldData(Model $model, array $fields = []): array
    {
        if (empty($fields)) {
            // Get all fillable attributes
            $fields = $model->getFillable();
        }
        
        $oldData = [];
        foreach ($fields as $field) {
            $oldData[$field] = $model->getAttribute($field);
        }
        
        return $oldData;
    }

    /**
     * Check if specific fields have changed.
     *
     * @param array $oldData
     * @param array $newData
     * @param array $fields Fields to check (empty array = all fields)
     * @return bool
     */
    protected function hasFieldsChanged(array $oldData, array $newData, array $fields = []): bool
    {
        if (empty($fields)) {
            $fields = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
        }
        
        foreach ($fields as $field) {
            $oldValue = $oldData[$field] ?? null;
            $newValue = $newData[$field] ?? null;
            
            // Handle numeric comparison
            if (is_numeric($oldValue) && is_numeric($newValue)) {
                if ((float) $oldValue !== (float) $newValue) {
                    return true;
                }
            } elseif ($oldValue !== $newValue) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log an operation for auditing.
     * 
     * Override in child classes to implement specific audit logging.
     *
     * @param string $action Action performed (create, update, delete, etc.)
     * @param Model $model
     * @param array $data Additional data to log
     * @return void
     */
    protected function logOperation(string $action, Model $model, array $data = []): void
    {
        Log::info("{$action} operation", [
            'model' => get_class($model),
            'model_id' => $model->id,
            'data' => $data,
        ]);
    }
}

