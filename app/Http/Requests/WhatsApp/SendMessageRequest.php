<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Send Message Request
 * 
 * Validates request for sending WhatsApp messages.
 */
class SendMessageRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     * 
     * Decodes JSON-encoded FormData fields into native arrays.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeJsonFields(['product_ids', 'customer_ids', 'product_types', 'content_variables']);
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
        $pdfType = $this->get('pdf_type', 'regular');
        $sendToAll = $this->boolean('send_to_all', false);

        $rules = [
            'send_to_all' => ['sometimes', 'boolean'],
            'customer_ids' => $sendToAll ? ['nullable'] : ['required'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'include_pdf' => ['required', 'boolean'],
            'template_id' => ['nullable', 'string'],
            'content_variables' => ['nullable'],
            'pdf_type' => ['sometimes', 'string', 'in:regular,custom'],
            'pdf_layout' => ['sometimes', 'string', 'in:regular,catalog'],
            'async' => ['sometimes', 'boolean'],
        ];

        // Conditional validation based on PDF type
        if ($pdfType === 'regular') {
            $rules['product_ids'] = ['required', 'array', 'min:1'];
            $rules['product_ids.*'] = ['required', 'integer', 'exists:products,id'];
        } else {
            $rules['custom_pdf'] = ['required', 'file', 'mimes:pdf', 'max:10240'];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'customer_ids.required' => 'Please select at least one customer or enable "send to all".',
            'product_ids.required' => 'Please select at least one product for the price list.',
            'custom_pdf.required' => 'Please upload a PDF file.',
            'custom_pdf.mimes' => 'Only PDF files are allowed.',
            'custom_pdf.max' => 'PDF file size must not exceed 10MB.',
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

            // Validate customer_ids if provided and not sending to all
            if (!$this->boolean('send_to_all') && $this->has('customer_ids')) {
                $customerIds = $this->getCustomerIds();
                if ($customerIds !== null && !empty($customerIds)) {
                    if (!is_array($customerIds)) {
                        $validator->errors()->add('customer_ids', 'customer_ids must be an array');
                    } else {
                        foreach ($customerIds as $id) {
                            if (!is_numeric($id) || !\App\Models\Customer::where('id', $id)->exists()) {
                                $validator->errors()->add('customer_ids', 'One or more customer IDs are invalid');
                                break;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Get customer IDs from request.
     *
     * @return array|null
     */
    public function getCustomerIds(): ?array
    {
        $customerIds = $this->input('customer_ids');
        
        if (empty($customerIds)) {
            return null;
        }

        if (is_string($customerIds)) {
            return json_decode($customerIds, true);
        }

        return is_array($customerIds) ? $customerIds : null;
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

