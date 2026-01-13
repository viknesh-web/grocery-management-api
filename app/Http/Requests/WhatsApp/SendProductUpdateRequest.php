<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'product_types' => ['sometimes', 'array'],
            'product_types.*' => ['string', 'in:daily,standard'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'include_pdf' => ['required', 'boolean'],
            'template_id' => ['nullable', 'string'],
            'content_variables' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'Please select at least one product',
            'product_ids.*.exists' => 'One or more selected products do not exist',
            'product_types.*.in' => 'Invalid product type. Must be either daily or standard',
            'include_pdf.required' => 'Please specify whether to include PDF',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle JSON-encoded arrays from FormData
        if ($this->has('product_ids') && is_string($this->product_ids)) {
            $this->merge([
                'product_ids' => json_decode($this->product_ids, true),
            ]);
        }

        if ($this->has('product_types') && is_string($this->product_types)) {
            $this->merge([
                'product_types' => json_decode($this->product_types, true),
            ]);
        }

        if ($this->has('content_variables') && is_string($this->content_variables)) {
            $this->merge([
                'content_variables' => json_decode($this->content_variables, true),
            ]);
        }
    }
}