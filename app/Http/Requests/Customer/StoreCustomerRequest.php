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

        // Normalize address
        if ($this->has('address')) {
            $addressValue = $this->input('address');
            $dataToMerge['address'] = ($addressValue !== null && $addressValue !== '') 
                ? trim((string) $addressValue) 
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

        if ($this->has('active')) {
            $dataToMerge['active'] = $this->boolean('active');
        } else {
            $dataToMerge['status'] = 'active';
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
        // Get validation country from config (default: AE for UAE)
        $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
        
        $rules = [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[a-zA-Z\s\-\']+$/',
            ],
            'whatsapp_number' => [
                'required',
                'string',
                'regex:' . $countryRules['regex'],
                'unique:customers,whatsapp_number',
            ],
            'landmark' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'address' => ['nullable', 'string', 'max:1000'],
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


