<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
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
            'sort' => 'sometimes|string|in:asc,desc|nullable',
            'orderby' => 'sometimes|string|nullable',
            'sort_by' => 'sometimes|string|nullable',
            'sort_order' => 'sometimes|string|in:asc,desc|nullable',
            
            // Filters
            'search' => 'sometimes|string|max:255',
            'query' => 'sometimes|string|max:255|nullable',
            'category' => 'sometimes|integer|exists:categories,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'status' => 'sometimes|string|in:active,inactive',
            'product_type' => 'sometimes|string|in:standard,daily',
            'has_discount' => 'sometimes|boolean',
            'stock_status' => 'sometimes|string|in:in_stock,low_stock,out_of_stock',
        ];
    }

    /**
     * Get filters normalized to backend format.
     */
    public function getFilters(): array
    {
        $filters = [];

        // Search
        if ($this->filled('query')) {
            $filters['search'] = $this->input('query');
        } elseif ($this->filled('search')) {
            $filters['search'] = $this->input('search');
        }

        // Category (frontend: 'category', backend: 'category_id')
        if ($this->has('category') && $this->filled('category')) {
            $filters['category_id'] = (int) $this->input('category');
        } elseif ($this->has('category_id') && $this->filled('category_id')) {
            $filters['category_id'] = (int) $this->input('category_id');
        }

        // Status
        if ($this->has('status') && $this->filled('status')) {
            $filters['status'] = $this->input('status');
        }

        // Product Type
        if ($this->has('product_type') && $this->filled('product_type')) {
            $filters['product_type'] = $this->input('product_type');
        }

        // Has Discount
        if ($this->has('has_discount')) {
            $filters['has_discount'] = $this->boolean('has_discount');
        }

        // Stock Status
        if ($this->has('stock_status') && $this->filled('stock_status')) {
            $filters['stock_status'] = $this->input('stock_status');
        }

        // Sorting (frontend: 'orderby', backend: 'sort_by')
        if ($this->has('orderby') && $this->filled('orderby')) {
            $filters['sort_by'] = $this->input('orderby');
        } elseif ($this->has('sort_by') && $this->filled('sort_by')) {
            $filters['sort_by'] = $this->input('sort_by');
        }

        // Sort Order (frontend: 'sort', backend: 'sort_order')
        if ($this->has('sort') && $this->filled('sort')) {
            $filters['sort_order'] = $this->input('sort');
        } elseif ($this->has('sort_order') && $this->filled('sort_order')) {
            $filters['sort_order'] = $this->input('sort_order');
        }

        return $filters;
    }

    /**
     * Get pagination parameters.
     */
    public function getPagination(): array
    {
        $page = $this->input('page', 1);
        
        // Frontend: 'limit', Backend: 'per_page'
        $perPage = $this->input('limit') ?? $this->input('per_page', 15);

        return [
            'page' => (int) $page,
            'per_page' => (int) $perPage,
        ];
    }
}