<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Generate Price List Request
 * 
 * Validates request for generating price list PDF.
 */
class GeneratePriceListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'pdf_layout' => ['sometimes', 'string', 'in:regular,catalog'],
        ];
    }
}

