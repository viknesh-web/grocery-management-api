<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class ValidateNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string'],
            'whatsapp_number' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => 'Phone number is required',
        ];
    }

    /**
     * Get validated phone number (supports both field names).
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        // Use whatsapp_number if provided, otherwise use phone_number
        $phoneNumber = $validated['whatsapp_number'] ?? $validated['phone_number'];
        
        return [
            'phone_number' => $phoneNumber,
        ];
    }
}