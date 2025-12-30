<?php

namespace App\Validator;

use App\Models\Product;
use Illuminate\Validation\Rule;

class ProductValidator
{
    private static function baseRules(): array
    {
        return [
            'name' => ['string', 'min:2', 'max:255'],
            'item_code' => ['string', 'max:100'],
            'category_id' => ['integer', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'regular_price' => ['numeric', 'min:0', 'max:999999.99'],
            'discount_type' => [Rule::in(['percentage', 'fixed', 'none'])],
            'discount_start_date' => ['nullable', 'date'],
            'discount_end_date' => ['nullable', 'date', 'after_or_equal:discount_start_date'],
            'stock_quantity' => ['numeric', 'min:0', 'max:999999.99'],
            'stock_unit' => [Rule::in(['Kg', 'Pieces', 'Units', 'L'])],
            'status' => [Rule::in(['active', 'inactive'])],
            'product_type' => [Rule::in(['daily', 'standard'])],
        ];
    }

    public static function onCreate(): array
    {
        return array_merge(self::baseRules(), [
            'name' => ['required'],
            'item_code' => ['required', Rule::unique('products', 'item_code')],
            'category_id' => ['required'],
            'regular_price' => ['required'],
            'stock_quantity' => ['required'],
            'stock_unit' => ['required'],
            'discount_type' => ['sometimes'],
            'discount_value' => self::discountRule(),
        ]);
    }

    public static function onIndex(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'product_type' => ['sometimes', 'string', 'in:daily,standard'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'has_discount' => ['sometimes', 'boolean'],
            'stock_status' => ['sometimes', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['sometimes', 'string', 'in:name,regular_price,selling_price,stock_quantity,created_at,product_type'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public static function onUpdate(int $id): array
    {
        return array_merge(self::baseRules(), [
            'name' => ['sometimes', 'required'],
            'item_code' => ['sometimes', 'required', Rule::unique('products', 'item_code')->ignore($id)],
            'category_id' => ['sometimes', 'nullable'],
            'original_price' => ['sometimes', 'required'],
            'stock_quantity' => ['sometimes', 'required'],
            'stock_unit' => ['sometimes'],
            'image_removed' => ['sometimes', 'boolean'],
            'discount_type' => ['sometimes'],
            'discount_value' => self::discountRule($id),
        ]);
    }

    private static function discountRule(?int $id = null): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            function ($attribute, $value, $fail) use ($id) {
                $request = request();
                $type = $request->input('discount_type', 'none');

                if ($type === 'none') {
                    return;
                }

                if ($value === null) {
                    $fail('The discount value is required when discount type is not "none".');
                    return;
                }

                if ($type === 'percentage' && $value > 100) {
                    $fail('The discount percentage cannot exceed 100%.');
                }

                if ($type === 'fixed') {
                    $price = $request->input('regular_price');

                    if (!$price && $id) {
                        $price = Product::whereKey($id)->value('regular_price');
                    }

                    if ($price && $value >= $price) {
                        $fail('The fixed discount must be less than the regular price.');
                    }
                }
            },
        ];
    }
}


