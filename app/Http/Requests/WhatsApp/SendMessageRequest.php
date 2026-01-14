<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sendToAll = $this->boolean('send_to_all', false);
        $pdfType = $this->get('pdf_type', 'regular');
        $includePdf = $this->boolean('include_pdf', false);

        $rules = [
            'send_to_all' => ['sometimes', 'boolean','nullable'],
            'customer_ids' => $sendToAll ? ['nullable', 'array'] : ['required', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'include_pdf' => ['sometimes', 'boolean'],
            'template_id' => ['nullable', 'string'],
            'content_variables' => ['nullable', 'array'],
            'pdf_type' => ['sometimes', 'string', 'in:regular,custom'],
            'pdf_layout' => ['sometimes', 'string', 'in:regular,catalog'],
        ];

        if ($pdfType === 'regular') {
            $rules['product_ids'] = ['nullable', 'array'];
            $rules['product_ids.*'] = ['integer', 'exists:products,id'];
        }

        // Only validate custom_pdf when pdf_type is custom AND include_pdf is true
        if ($pdfType === 'custom' && $includePdf) {
            $rules['custom_pdf'] = ['required', 'file', 'mimes:pdf', 'max:10240'];
        } else {
            $rules['custom_pdf'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'customer_ids.required' => 'Please select at least one customer',
            'customer_ids.*.exists' => 'One or more selected customers do not exist',
            'product_ids.*.exists' => 'One or more selected products do not exist',
            'custom_pdf.required' => 'The custom PDF file is required when PDF type is custom',
            'custom_pdf.file' => 'The custom PDF must be a valid file',
            'custom_pdf.mimes' => 'The file must be a PDF',
            'custom_pdf.max' => 'The PDF file must not exceed 10MB',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle JSON-encoded arrays from FormData
        if ($this->has('customer_ids') && is_string($this->customer_ids)) {
            $this->merge([
                'customer_ids' => json_decode($this->customer_ids, true) ?? [],
            ]);
        }

        if ($this->has('product_ids') && is_string($this->product_ids)) {
            $this->merge([
                'product_ids' => json_decode($this->product_ids, true) ?? [],
            ]);
        }

        if ($this->has('content_variables') && is_string($this->content_variables)) {
            $this->merge([
                'content_variables' => json_decode($this->content_variables, true) ?? [],
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Log validation errors for debugging
        Log::error('WhatsApp Message Validation Failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['custom_pdf']), // Don't log file content
            'has_file' => $this->hasFile('custom_pdf'),
            'pdf_type' => $this->get('pdf_type'),
            'include_pdf' => $this->get('include_pdf'),
        ]);

        parent::failedValidation($validator);
    }
}