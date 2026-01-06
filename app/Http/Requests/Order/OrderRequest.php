<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Index Order Request
 * 
 * Validates query parameters for listing orders with advanced filters.
 */
class OrderRequest extends FormRequest
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


    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'confirmed', 'processing', 'completed', 'cancelled'])],
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'customer_address' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['sometimes', 'string', Rule::in(['order_number', 'customer_name', 'total', 'status', 'order_date', 'created_at'])],
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
            'status' => $this->get('status'),
            'customer_ids' => $this->get('customer_ids'),
            'customer_address' => $this->get('customer_address'),
            'date_from' => $this->get('date_from'),
            'date_to' => $this->get('date_to'),
            'sort_by' => $this->get('sort_by', 'created_at'),
            'sort_order' => $this->get('sort_order', 'desc'),
        ];
    }

    public function getPagination(): array
    {
        return [
            'page' => $this->get('page', 0), 
            'limit' => $this->get('limit', 10),
        ];
    }
}