<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Customer Request
 * 
 * Validates and normalizes data for creating a new customer.
 */
class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $dataToMerge = [];

        // Normalize name
        if ($this->has('name') && $this->name !== null && $this->name !== '') {
            $dataToMerge['name'] = trim((string) $this->name);
        }

        // Normalize WhatsApp number
        if ($this->has('whatsapp_number') && $this->whatsapp_number !== null && $this->whatsapp_number !== '') {
            $whatsappNumber = trim((string) $this->whatsapp_number);
            
            // Remove spaces, dashes, and other formatting
            $whatsappNumber = preg_replace('/[\s\-\(\)]/', '', $whatsappNumber);
            
            // Add + if not present and starts with country code
            if (!str_starts_with($whatsappNumber, '+')) {
                // If starts with 91 (India), add +
                if (str_starts_with($whatsappNumber, '91')) {
                    $whatsappNumber = '+' . $whatsappNumber;
                } elseif (str_starts_with($whatsappNumber, '0')) {
                    // Remove leading 0 and add +91
                    $whatsappNumber = '+91' . substr($whatsappNumber, 1);
                } else {
                    // Assume Indian number, add +91
                    $whatsappNumber = '+91' . $whatsappNumber;
                }
            }
            
            $dataToMerge['whatsapp_number'] = $whatsappNumber;
        }

        // Normalize address/area
        if ($this->has('address') && $this->address !== null && $this->address !== '') {
            $dataToMerge['address'] = trim((string) $this->address);
        }

        if ($this->has('area') && $this->area !== null && $this->area !== '') {
            $dataToMerge['area'] = trim((string) $this->area);
        }

        // Normalize landmark
        if ($this->has('landmark') && $this->landmark !== null && $this->landmark !== '') {
            $dataToMerge['landmark'] = trim((string) $this->landmark);
        }

        // Normalize remarks
        if ($this->has('remarks') && $this->remarks !== null && $this->remarks !== '') {
            $dataToMerge['remarks'] = trim((string) $this->remarks);
        }

        // Normalize active with default
        if ($this->has('active')) {
            $dataToMerge['active'] = filter_var($this->active, FILTER_VALIDATE_BOOLEAN) ?: true;
        } else {
            $dataToMerge['active'] = true;
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'whatsapp_number' => [
                'required',
                'string',
                'regex:/^\+971(2|3|4|6|7|9|50|52|54|55|56|58)\d{7}$/',
                'unique:customers,whatsapp_number',
            ],
            'landmark' => ['nullable', 'string', 'max:255'],
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


