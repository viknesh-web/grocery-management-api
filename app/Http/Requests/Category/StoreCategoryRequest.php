<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * Store Category Request
 * 
 * Validates and normalizes data for creating a new category.
 */
class StoreCategoryRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $dataToMerge = [];

        // Normalize name
        if ($this->has('name') && $this->name !== null && $this->name !== '') {
            $dataToMerge['name'] = trim((string) $this->name);
            
            // Auto-generate slug from name if not provided
            if (!isset($this->slug) || empty($this->slug)) {
                $dataToMerge['slug'] = Str::slug($dataToMerge['name']);
            }
        }

        // Normalize slug
        if ($this->has('slug') && $this->slug !== null && $this->slug !== '') {
            $dataToMerge['slug'] = Str::slug(trim((string) $this->slug));
        }

        // Normalize description
        if ($this->has('description') && $this->description !== null && $this->description !== '') {
            $dataToMerge['description'] = trim((string) $this->description);
        }

        if ($this->has('is_active')) {
            $dataToMerge['is_active'] = $this->boolean('is_active');
        } else {
            $dataToMerge['is_active'] = true;
        }

        // Normalize display_order with default
        if ($this->has('display_order') && $this->display_order !== null && $this->display_order !== '') {
            $dataToMerge['display_order'] = (int) $this->display_order;
        } else {
            $dataToMerge['display_order'] = 0;
        }

        // Normalize parent_id
        if ($this->has('parent_id')) {
            if ($this->parent_id !== null && $this->parent_id !== '') {
                $dataToMerge['parent_id'] = (int) $this->parent_id;
            } else {
                $dataToMerge['parent_id'] = null;
            }
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
        ];
    }
}



