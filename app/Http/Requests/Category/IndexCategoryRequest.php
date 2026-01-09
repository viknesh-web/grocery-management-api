<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Index Category Request - Updated to handle frontend query params
 */
class IndexCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pagination (frontend or backend style)
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
            
            // Sorting (frontend or backend style)
            'sort' => 'sometimes|nullable|string|in:asc,desc',
            'orderby' => 'sometimes|string|nullable',
            'sort_by' => 'sometimes|string|nullable',
            'sort_order' => 'sometimes|string|in:asc,desc|nullable',
            
            // Filters
            'search' => 'sometimes|string|max:255',
            'query' => 'sometimes|string|max:255|nullable',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }

    /**
     * Get filters normalized to backend format.
     *
     * @return array
     */
    public function getFilters(): array
    {
        $filters = [];

        // Accept either `query` or `search`
        if ($this->filled('query')) {
            $filters['search'] = $this->input('query');
        } elseif ($this->filled('search')) {
            $filters['search'] = $this->input('search');
        }

        // Status
        if ($this->filled('status')) {
            $filters['status'] = $this->input('status');
        }

        // Sorting
        if ($this->filled('orderby')) {
            $filters['sort_by'] = $this->input('orderby');
        } elseif ($this->filled('sort_by')) {
            $filters['sort_by'] = $this->input('sort_by');
        }

        if ($this->filled('sort')) {
            $filters['sort_order'] = $this->input('sort');
        } elseif ($this->filled('sort_order')) {
            $filters['sort_order'] = $this->input('sort_order');
        }

        return $filters;
    }


    /**
     * Get pagination parameters.
     *
     * @return array
     */
    public function getPagination(): array
    {
        $page = $this->input('page', 1);
        $perPage = $this->input('limit') ?? $this->input('per_page', 15);

        return [
            'page' => (int) $page,
            'per_page' => (int) $perPage,
        ];
    }
}