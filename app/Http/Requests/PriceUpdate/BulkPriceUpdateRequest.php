<?php

namespace App\Http\Requests\PriceUpdate;

use Illuminate\Foundation\Http\FormRequest;

class BulkPriceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated (handled by auth:sanctum middleware)
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'updates.*.regular_price' => ['nullable', 'numeric', 'min:0'],
            'updates.*.discount_type' => ['nullable', 'string', 'in:none,percentage,fixed'],
            'updates.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'updates.*.stock_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
