<?php

namespace App\Validator;

use Illuminate\Validation\Rule;

/**
 * Customer Validator
 * 
 * Provides validation rules for customer operations.
 */
class CustomerValidator
{
    /**
     * Get validation rules for creating a customer.
     *
     * @return array<string, array>
     */
    public static function onCreate(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'whatsapp_number' => [
                'required',
                'string',
                'regex:/^\+91[6-9]\d{9}$/',
                'unique:customers,whatsapp_number',
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];

        // Conditionally add address or area field based on feature flag
        if (config('features.address_field')) {
            $rules['area'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['address'] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * Get validation rules for editing a customer.
     *
     * @param int|null $id The customer ID to exclude from unique checks
     * @return array<string, array>
     */
    public static function onEdit(?int $id = null): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'min:2'],
            'whatsapp_number' => [
                'sometimes',
                'required',
                'string',
                'regex:/^\+91[6-9]\d{9}$/',
                Rule::unique('customers', 'whatsapp_number')->ignore($id),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];

        // Conditionally add address or area field based on feature flag
        if (config('features.address_field')) {
            $rules['area'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['address'] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }
}



