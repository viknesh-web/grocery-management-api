<?php

namespace App\Validator;

use Illuminate\Validation\Rule;

/**
 * Base Validator Class
 * 
 * Provides common validation rules and methods for all validators.
 * All validator classes should extend this base class.
 * 
 * @package App\Validator
 */
abstract class BaseValidator
{
    /**
     * Get validation rules for creating a new resource.
     * 
     * @return array<string, array<int, mixed>>
     */
    abstract public static function onCreate(): array;

    /**
     * Get validation rules for updating an existing resource.
     * 
     * @param int|null $id The ID of the resource being updated
     * @return array<string, array<int, mixed>>
     */
    abstract public static function onUpdate(?int $id = null): array;

    /**
     * Get common image validation rules.
     * 
     * @param bool $required Whether the image is required
     * @return array<int, mixed>
     */
    protected static function imageRules(bool $required = false): array
    {
        $rules = ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'];

        if ($required) {
            $rules[0] = 'required';
        }

        return $rules;
    }

    /**
     * Get common status validation rules.
     * 
     * @param array<string> $allowedValues The allowed status values (default: ['active', 'inactive'])
     * @return array<int, mixed>
     */
    protected static function statusRules(array $allowedValues = ['active', 'inactive']): array
    {
        return ['sometimes', 'string', Rule::in($allowedValues)];
    }

    /**
     * Get common name validation rules.
     * 
     * @param bool $required Whether the name is required
     * @param int $min Minimum length (default: 2)
     * @param int $max Maximum length (default: 255)
     * @return array<int, mixed>
     */
    protected static function nameRules(bool $required = true, int $min = 2, int $max = 255): array
    {
        $rules = ['string', "min:{$min}", "max:{$max}"];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'sometimes');
            $rules[] = 'required'; // Make it required if present
        }

        return $rules;
    }

    /**
     * Get common description validation rules.
     * 
     * @param bool $required Whether the description is required
     * @param int $max Maximum length (default: 1000)
     * @return array<int, mixed>
     */
    protected static function descriptionRules(bool $required = false, int $max = 1000): array
    {
        $rules = ['nullable', 'string', "max:{$max}"];

        if ($required) {
            $rules[0] = 'required';
        }

        return $rules;
    }

    /**
     * Get common pagination validation rules.
     * 
     * @return array<string, array<int, mixed>>
     */
    protected static function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get common sorting validation rules.
     * 
     * @param array<string> $allowedFields The allowed sort fields
     * @return array<string, array<int, mixed>>
     */
    protected static function sortingRules(array $allowedFields): array
    {
        return [
            'sort_by' => ['sometimes', 'string', Rule::in($allowedFields)],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get validation error messages.
     * 
     * Override this method in child classes to provide custom messages.
     * 
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }
}

