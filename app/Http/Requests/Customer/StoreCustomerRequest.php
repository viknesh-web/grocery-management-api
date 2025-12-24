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
            
            // Get valid country codes from environment
            $validCodes = array_map('trim', array_filter(explode(',', env('VITE_VALID_COUNTRY_CODES', 'UAE,IN'))));
            $validCodes = array_map('strtoupper', $validCodes);
            
            // Add + if not present
            if (!str_starts_with($whatsappNumber, '+')) {
                // If starts with 971 (UAE), add +
                if (str_starts_with($whatsappNumber, '971')) {
                    $whatsappNumber = '+' . $whatsappNumber;
                }
                // If starts with 91 (India), add +
                elseif (str_starts_with($whatsappNumber, '91')) {
                    $whatsappNumber = '+' . $whatsappNumber;
                }
                // If starts with 0, determine country code based on valid codes
                elseif (str_starts_with($whatsappNumber, '0')) {
                    if (in_array('IN', $validCodes)) {
                        // Remove leading 0 and add +91 for India
                        $whatsappNumber = '+91' . substr($whatsappNumber, 1);
                    } elseif (in_array('UAE', $validCodes)) {
                        // Remove leading 0 and add +971 for UAE (if number format suggests UAE)
                        $whatsappNumber = '+971' . substr($whatsappNumber, 1);
                    } else {
                        // Default to India if not specified
                        $whatsappNumber = '+91' . substr($whatsappNumber, 1);
                    }
                }
                // If no country code prefix, determine based on length and valid codes
                else {
                    // 10 digits likely India, 9 digits likely UAE
                    if (strlen($whatsappNumber) === 10 && in_array('IN', $validCodes)) {
                        $whatsappNumber = '+91' . $whatsappNumber;
                    } elseif (strlen($whatsappNumber) === 9 && in_array('UAE', $validCodes)) {
                        $whatsappNumber = '+971' . $whatsappNumber;
                    } elseif (in_array('IN', $validCodes)) {
                        // Default to India if both are valid, prefer India
                        $whatsappNumber = '+91' . $whatsappNumber;
                    } elseif (in_array('UAE', $validCodes)) {
                        $whatsappNumber = '+971' . $whatsappNumber;
                    }
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
        // Get valid country codes from environment
        $validCodes = array_map('trim', array_filter(explode(',', env('VITE_VALID_COUNTRY_CODES', 'UAE,IN'))));
        $validCodes = array_map('strtoupper', $validCodes);
        
        // Build phone validation regex based on valid country codes
        $phoneRegexPatterns = [];
        if (in_array('UAE', $validCodes)) {
            // UAE: +971 followed by 2,3,4,6,7,9 or 50,52,54,55,56,58, then 7 more digits
            $phoneRegexPatterns[] = '\+971(2|3|4|6|7|9|50|52|54|55|56|58)\d{7}';
        }
        if (in_array('IN', $validCodes)) {
            // India: +91 followed by 6-9, then 9 more digits (total 10 digits)
            $phoneRegexPatterns[] = '\+91[6-9]\d{9}';
        }
        
        // If no valid codes specified, default to both
        if (empty($phoneRegexPatterns)) {
            $phoneRegexPatterns[] = '\+971(2|3|4|6|7|9|50|52|54|55|56|58)\d{7}';
            $phoneRegexPatterns[] = '\+91[6-9]\d{9}';
        }
        
        // Combine patterns with OR
        $phoneRegex = '/^(' . implode('|', $phoneRegexPatterns) . ')$/';
        
        // Build error message
        $errorMessages = [];
        if (in_array('UAE', $validCodes) || empty($validCodes)) {
            $errorMessages[] = 'UAE (+971XXXXXXXXX)';
        }
        if (in_array('IN', $validCodes) || empty($validCodes)) {
            $errorMessages[] = 'India (+91XXXXXXXXXX)';
        }
        $phoneErrorMsg = 'The phone number must be a valid ' . implode(' or ', $errorMessages) . ' format.';

        $rules = [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'whatsapp_number' => [
                'required',
                'string',
                'unique:customers,whatsapp_number',
            ],
            'landmark' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
            // Always accept both address and area fields
            'address' => ['nullable', 'string', 'max:1000'],
            'area' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }
}


