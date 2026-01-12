<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Product Request - Updated with ProductDiscount Support
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dataToMerge = [];

        if ($this->has('name') && $this->name !== null && $this->name !== '') {
            $dataToMerge['name'] = trim((string) $this->name);
        }

        if ($this->has('item_code') && $this->item_code !== null && $this->item_code !== '') {
            $dataToMerge['item_code'] = strtoupper(trim((string) $this->item_code));
        }

        if ($this->has('regular_price') && $this->regular_price !== null && $this->regular_price !== '') {
            $dataToMerge['regular_price'] = (float) $this->regular_price;
        }

        if ($this->has('discount_value') && $this->discount_value !== null && $this->discount_value !== '') {
            $dataToMerge['discount_value'] = (float) $this->discount_value;
        }

        if ($this->has('stock_quantity') && $this->stock_quantity !== null && $this->stock_quantity !== '') {
            $dataToMerge['stock_quantity'] = (float) $this->stock_quantity;
        }

        if ($this->has('category_id') && $this->category_id !== null && $this->category_id !== '') {
            $dataToMerge['category_id'] = (int) $this->category_id;
        }

        if ($this->has('stock_unit') && $this->stock_unit !== null && $this->stock_unit !== '') {
            $dataToMerge['stock_unit'] = trim((string) $this->stock_unit);
        }

        if ($this->has('discount_type') && $this->discount_type !== null && $this->discount_type !== '') {
            $dataToMerge['discount_type'] = strtolower(trim((string) $this->discount_type));
        } else {
            $dataToMerge['discount_type'] = 'none';
        }

        if ($this->has('product_type') && $this->product_type !== null && $this->product_type !== '') {
            $dataToMerge['product_type'] = strtolower(trim((string) $this->product_type));
        } else {
            $dataToMerge['product_type'] = 'daily';
        }

        if ($this->has('min_quantity') && $this->min_quantity !== null && $this->min_quantity !== '') {
            $dataToMerge['min_quantity'] = trim((string) $this->min_quantity);
        }

        if ($this->has('max_quantity') && $this->max_quantity !== null && $this->max_quantity !== '') {
            $dataToMerge['max_quantity'] = trim((string) $this->max_quantity);
        }

        if ($this->has('status') && in_array($this->status, ['active', 'inactive'])) {
            $dataToMerge['status'] = $this->status;
        } else {
            $dataToMerge['status'] = 'active';
        }

        if (($dataToMerge['discount_type'] ?? 'none') === 'none') {
            $dataToMerge['discount_value'] = null;
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'item_code' => ['required', 'string', 'max:100', Rule::unique('products', 'item_code')],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'discount_type' => ['sometimes', Rule::in(['percentage', 'fixed', 'none'])],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $type = $this->input('discount_type', 'none');

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
                        $price = $this->input('regular_price');
                        if ($price && $value >= $price) {
                            $fail('The fixed discount must be less than the regular price.');
                        }
                    }
                },
            ],
            'discount_start_date' => ['nullable', 'date'],
            'discount_end_date' => ['nullable', 'date', 'after_or_equal:discount_start_date'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'stock_unit' => ['required', Rule::in(['Kg', 'Pieces', 'Units', 'L'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'min_quantity' => ['required', 'numeric', 'min:0'],
            'max_quantity' => ['numeric', 'min:0'],
            'product_type' => ['sometimes', Rule::in(['daily', 'standard'])],
        ];
    }
}