<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Customer Request
 * 
 * Validates and normalizes data for updating a customer.
 */
class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer')?->id ?? $this->input('id');
        
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
            
            // Get validation country from config (default: AE for UAE)
            $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
            $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
            $countryCode = $countryRules['country_code'];
            
            // Add + if not present
            if (!str_starts_with($whatsappNumber, '+')) {
                // If starts with country code without +, add +
                if (str_starts_with($whatsappNumber, ltrim($countryCode, '+'))) {
                    $whatsappNumber = '+' . $whatsappNumber;
                }
                // If starts with 0, remove leading 0 and add country code
                elseif (str_starts_with($whatsappNumber, '0')) {
                    $whatsappNumber = $countryCode . substr($whatsappNumber, 1);
                }
                // If no country code prefix, add country code
                else {
                    $whatsappNumber = $countryCode . $whatsappNumber;
                }
            }
            
            $dataToMerge['whatsapp_number'] = $whatsappNumber;
        }

        // Normalize address/area
        // Always accept both address and area fields regardless of feature flag
        if ($this->has('address')) {
            $addressValue = $this->input('address');
            $dataToMerge['address'] = ($addressValue !== null && $addressValue !== '') 
                ? trim((string) $addressValue) 
                : null;
        }

        if ($this->has('area')) {
            $areaValue = $this->input('area');
            $dataToMerge['area'] = ($areaValue !== null && $areaValue !== '') 
                ? trim((string) $areaValue) 
                : null;
        }

        // Normalize landmark
        if ($this->has('landmark') && $this->landmark !== null && $this->landmark !== '') {
            $dataToMerge['landmark'] = trim((string) $this->landmark);
        }

        // Normalize remarks
        if ($this->has('remarks') && $this->remarks !== null && $this->remarks !== '') {
            $dataToMerge['remarks'] = trim((string) $this->remarks);
        }

        // Normalize active (no default for updates)
        if ($this->has('active')) {
            $activeValue = $this->active;
            if (is_string($activeValue)) {
                $dataToMerge['active'] = filter_var($activeValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            } else {
                $dataToMerge['active'] = (bool) $activeValue;
            }
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
        $customerId = $this->route('customer')?->id ?? $this->input('id');

        // Get validation country from config (default: IN)
        $validationCountry = strtoupper(config('phone.validation_country', 'IN'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.IN'));

        $rules = [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\']+$/',
            ],
            'whatsapp_number' => [
                'sometimes',
                'required',
                'string',
                'regex:' . $countryRules['regex'],
                Rule::unique('customers', 'whatsapp_number')->ignore($customerId),
            ],
            'landmark' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
            // Always accept both address and area fields
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        return $rules;
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $validationCountry = strtoupper(config('phone.validation_country', 'IN'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.IN'));
        
        return [
            'whatsapp_number.regex' => $countryRules['error_message'],
            'name.regex' => 'Please enter a valid name (2-100 characters, letters only)',
        ];
    }
}


