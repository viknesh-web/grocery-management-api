<?php

namespace App\Validator;

use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryValidator extends BaseValidator
{
    public static function onCreate(): array
    {
        return [
            'name' => array_merge(
                self::nameRules(true),
                [Rule::unique('categories', 'name')]
            ),
            'description' => self::descriptionRules(),
            'image' => self::imageRules(false),
            'status' => self::statusRules(),
        ];
    }

    public static function onUpdate(?int $id = null): array
    {
        return [
            'name' => array_merge(
                self::nameRules(false),
                [Rule::unique('categories', 'name')->ignore($id)]
            ),
            'description' => self::descriptionRules(),
            'image' => self::imageRules(false),
            'image_removed' => ['sometimes', 'boolean'],
            'status' => self::statusRules(),
        ];
    }
}



