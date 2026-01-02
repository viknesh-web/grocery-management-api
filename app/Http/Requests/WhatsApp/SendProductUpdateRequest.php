<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Send Product Update Request
 * 
 * Validates request for sending WhatsApp product updates.
 */
class SendProductUpdateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     * 
     * Decodes JSON-encoded FormData fields into native arrays.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeJsonFields(['product_ids', 'product_types', 'content_variables']);
    }

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
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'product_types' => ['sometimes', 'array'],
            'product_types.*' => ['required', 'string', 'in:daily,standard'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'include_pdf' => ['required', 'boolean'],
            'template_id' => ['nullable', 'string'],
            'content_variables' => ['nullable', 'array'],
            'async' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that template_id and message are not both provided
            if (!empty($this->template_id) && !empty($this->message_template ?? $this->message)) {
                $validator->errors()->add('template_id', 'Cannot use template with plain text message');
                $validator->errors()->add('message', 'Cannot use plain text message with template');
            }

            // Validate that selected products match the product types (if provided)
            if ($this->has('product_types') && !empty($this->product_types)) {
                $productIds = $this->getProductIds();
                if ($productIds) {
                    $products = \App\Models\Product::whereIn('id', $productIds)->get();
                    $invalidProducts = $products->reject(function ($product) {
                        return in_array($product->product_type, $this->product_types ?? []);
                    });

                    if ($invalidProducts->isNotEmpty()) {
                        $validator->errors()->add('product_ids', 'Selected products must match the selected product types');
                    }
                }
            }
        });
    }

    /**
     * Get product IDs from request.
     *
     * @return array|null
     */
    public function getProductIds(): ?array
    {
        $productIds = $this->input('product_ids');
        
        if (empty($productIds)) {
            return null;
        }

        if (is_string($productIds)) {
            return json_decode($productIds, true);
        }

        return is_array($productIds) ? $productIds : null;
    }

    /**
     * Get content variables from request.
     *
     * @return array|null
     */
    public function getContentVariables(): ?array
    {
        $contentVariables = $this->input('content_variables');
        
        if (empty($contentVariables)) {
            return null;
        }

        if (is_string($contentVariables)) {
            return json_decode($contentVariables, true);
        }

        return is_array($contentVariables) ? $contentVariables : null;
    }

    /**
     * Decode JSON-encoded FormData fields into native arrays.
     *
     * @param array $fields
     * @return void
     */
    protected function normalizeJsonFields(array $fields): void
    {
        foreach ($fields as $field) {
            $value = $this->get($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }
}

