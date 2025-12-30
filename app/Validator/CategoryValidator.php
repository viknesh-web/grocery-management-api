<?php

namespace App\Validator;

use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryValidator
{
    public static function onCreate(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public static function onUpdate(?int $id = null): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'min:2', Rule::unique('categories', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'image_removed' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

}



