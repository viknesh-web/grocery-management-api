<?php

namespace App\Validator;

use Illuminate\Validation\Rule;

/**
 * Category Validator
 * 
 * Provides validation rules for category operations.
 */
class CategoryValidator
{
    /**
     * Get validation rules for creating a category.
     *
     * @return array<string, array>
     */
    public static function onCreate(): array
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
                'exists:categories,id'
            ],
        ];
    }

    /**
     * Get validation rules for editing a category.
     *
     * @param int|null $id The category ID to exclude from unique checks
     * @return array<string, array>
     */
    public static function onEdit(?int $id = null): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'min:2', Rule::unique('categories', 'name')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'image_removed' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
        ];
    }

    /**
     * Check if setting parent_id would create a circular reference.
     *
     * @param \App\Models\Category $category
     * @param int $newParentId
     * @return bool
     */
    protected static function wouldCreateCircularReference($category, int $newParentId): bool
    {
        $parent = \App\Models\Category::find($newParentId);
        if (!$parent) {
            return false;
        }

        // Check if the new parent is a descendant of the current category
        $currentParent = $parent;
        while ($currentParent && $currentParent->parent_id) {
            if ($currentParent->parent_id == $category->id) {
                return true;
            }
            $currentParent = \App\Models\Category::find($currentParent->parent_id);
        }

        return false;
    }
}



