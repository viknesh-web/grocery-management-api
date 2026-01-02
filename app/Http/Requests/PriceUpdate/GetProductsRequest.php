<?php

namespace App\Http\Requests\PriceUpdate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Get Products Request
 * 
 * Validates request for getting products for price updates.
 */
class GetProductsRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'string'], // Supports single ID, comma-separated, or array
            'product_type' => ['sometimes', 'string'], // Supports single type, comma-separated, or array
        ];
    }

    /**
     * Get filters from validated request.
     *
     * @return array
     */
    public function getFilters(): array
    {
        $filters = [];

        if ($this->has('search')) {
            $filters['search'] = $this->input('search');
        }

        if ($this->has('category_id')) {
            $filters['category_id'] = $this->normalizeIds($this->input('category_id'));
        }

        if ($this->has('product_type')) {
            $filters['product_type'] = $this->normalizeTypes($this->input('product_type'));
        }

        return $filters;
    }

    /**
     * Normalize category IDs from various formats.
     *
     * @param mixed $input
     * @return array|int|null
     */
    protected function normalizeIds($input)
    {
        if (is_string($input) && strpos($input, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $input)));
        }
        
        if (is_array($input) && !empty($input)) {
            return $input;
        }
        
        if (!empty($input)) {
            return $input;
        }

        return null;
    }

    /**
     * Normalize product types from various formats.
     *
     * @param mixed $input
     * @return array|string|null
     */
    protected function normalizeTypes($input)
    {
        if (is_string($input) && strpos($input, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $input)));
        }
        
        if (is_array($input) && !empty($input)) {
            return $input;
        }
        
        if (!empty($input)) {
            return $input;
        }

        return null;
    }
}

