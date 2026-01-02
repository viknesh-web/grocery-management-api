<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Index Product Request
 * 
 * Validates query parameters for listing products.
 */
class IndexProductRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'product_type' => ['sometimes', 'string', 'in:daily,standard'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'has_discount' => ['sometimes', 'boolean'],
            'stock_status' => ['sometimes', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['sometimes', 'string', Rule::in(['name', 'regular_price', 'selling_price', 'stock_quantity', 'created_at', 'product_type'])],
            'sort_order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get validated filters for service layer.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return [
            'search' => $this->get('search'),
            'category_id' => $this->get('category_id'),
            'product_type' => $this->get('product_type'),
            'status' => $this->get('status'),
            'has_discount' => $this->get('has_discount'),
            'stock_status' => $this->get('stock_status'),
            'sort_by' => $this->get('sort_by', 'created_at'),
            'sort_order' => $this->get('sort_order', 'desc'),
        ];
    }
}

