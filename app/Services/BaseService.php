<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base Service
 * 
 * Abstract base class providing common service patterns:
 */
abstract class BaseService
{
   
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

    protected function prepareData(array $data, ?int $userId = null): array
    {
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

    protected function getOldData(Model $model, array $fields = []): array
    {
        if (empty($fields)) {
            $fields = $model->getFillable();
        }
        
        $oldData = [];
        foreach ($fields as $field) {
            $oldData[$field] = $model->getAttribute($field);
        }
        
        return $oldData;
    }

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
}

