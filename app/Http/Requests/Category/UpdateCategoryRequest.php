<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Category Request
 * 
 * Validates and normalizes data for updating a category.
 */
class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id ?? $this->input('id');
        
        $dataToMerge = [];

        // Normalize name
        if ($this->has('name') && $this->name !== null && $this->name !== '') {
            $dataToMerge['name'] = trim((string) $this->name);
        }

        // Normalize description
        if ($this->has('description') && $this->description !== null && $this->description !== '') {
            $dataToMerge['description'] = trim((string) $this->description);
        }

        // Normalize status (no default for updates)
        if ($this->has('status') && in_array($this->status, ['active', 'inactive'])) {
            $dataToMerge['status'] = $this->status;
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
            'name' => ['sometimes', 'required', 'string', 'max:255', 'min:2'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'image_removed' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}



