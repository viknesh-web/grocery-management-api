<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Index Category Request
 * 
 * Validates query parameters for listing categories.
 */
class IndexCategoryRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'active' => ['sometimes', 'boolean'], // Legacy filter, converted to status
            'sort_by' => ['sometimes', 'string', Rule::in(['name', 'created_at'])],
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
            'active' => $this->get('active'), // Legacy support
            'sort_by' => $this->get('sort_by', 'name'),
            'sort_order' => $this->get('sort_order', 'asc'),
        ];
    }
}

