<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Product Request
 * 
 * Validates and normalizes data for updating a product.
 */
class UpdateProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id ?? $this->input('id');
        
        $dataToMerge = [];

        // Normalize name
        if ($this->has('name') && $this->name !== null && $this->name !== '') {
            $dataToMerge['name'] = trim((string) $this->name);
        }

        // Normalize item_code (uppercase)
        if ($this->has('item_code') && $this->item_code !== null && $this->item_code !== '') {
            $dataToMerge['item_code'] = strtoupper(trim((string) $this->item_code));
        }

        // Normalize prices
        if ($this->has('original_price') && $this->original_price !== null && $this->original_price !== '') {
            $dataToMerge['original_price'] = (float) $this->original_price;
        }

        if ($this->has('discount_value') && $this->discount_value !== null && $this->discount_value !== '') {
            $dataToMerge['discount_value'] = (float) $this->discount_value;
        }

        if ($this->has('stock_quantity') && $this->stock_quantity !== null && $this->stock_quantity !== '') {
            $dataToMerge['stock_quantity'] = (float) $this->stock_quantity;
        }

        // Normalize category_id
        if ($this->has('category_id') && $this->category_id !== null && $this->category_id !== '') {
            $dataToMerge['category_id'] = (int) $this->category_id;
        }

        // Normalize stock_unit (keep original casing like store request)
        if ($this->has('stock_unit') && $this->stock_unit !== null && $this->stock_unit !== '') {
            $dataToMerge['stock_unit'] = trim((string) $this->stock_unit);
        }

        // Normalize discount_type (lowercase)
        if ($this->has('discount_type') && $this->discount_type !== null && $this->discount_type !== '') {
            $dataToMerge['discount_type'] = strtolower(trim((string) $this->discount_type));
        }

        // Normalize product_type (lowercase)
        if ($this->has('product_type') && $this->product_type !== null && $this->product_type !== '') {
            $dataToMerge['product_type'] = strtolower(trim((string) $this->product_type));
        }

        // Normalize enabled (no default for updates)
        if ($this->has('enabled')) {
            $enabledValue = $this->enabled;
            if (is_string($enabledValue)) {
                $dataToMerge['enabled'] = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            } else {
                $dataToMerge['enabled'] = (bool) $enabledValue;
            }
        }

        // Clear discount_value if discount_type is 'none'
        $discountType = $dataToMerge['discount_type'] ?? $this->input('discount_type') ?? null;
        if ($discountType === 'none') {
            $dataToMerge['discount_value'] = null;
        }

        // Normalize variations.enabled boolean values (FormData sends as string "true"/"false" or "1"/"0")
        if ($this->has('variations') && is_array($this->variations)) {
            foreach ($this->variations as $index => $variation) {
                if (isset($variation['enabled'])) {
                    $enabledValue = $variation['enabled'];
                    // Convert to boolean: accept true, 1, "1", "true", "on", "yes" as true; everything else as false
                    $normalized = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($normalized === null) {
                        // If filter_var returns null, try manual conversion
                        $normalized = in_array($enabledValue, [1, '1', 'true', 'on', 'yes', true], true);
                    }
                    $this->merge([
                        "variations.{$index}.enabled" => (bool) $normalized
                    ]);
                }
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
        $productId = $this->route('product')?->id ?? $this->input('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'item_code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'item_code')->ignore($productId)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
                function ($attribute, $value, $fail) {
                    // If image is removed, a new image must be provided
                    $imageRemoved = $this->boolean('image_removed', false);
                    if ($imageRemoved && !$value) {
                        $fail('A new image is required when removing the existing image.');
                    }
                },
            ],
            'image_removed' => ['sometimes', 'boolean'],
            'original_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'discount_type' => ['sometimes', Rule::in(['percentage', 'fixed', 'none'])],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($productId) {
                    $type = $this->input('discount_type');

                    if ($type === 'none') {
                        return;
                    }

                    if ($type && $type !== 'none' && $value === null) {
                        $fail('The discount value is required when discount type is not "none".');
                        return;
                    }

                    if ($type === 'percentage' && $value > 100) {
                        $fail('The discount percentage cannot exceed 100%.');
                    }

                    if ($type === 'fixed') {
                        $price = $this->input('original_price');
                        if (!$price && $productId) {
                            $price = Product::whereKey($productId)->value('original_price');
                        }
                        if ($price && $value >= $price) {
                            $fail('The fixed discount must be less than the regular price.');
                        }
                    }
                },
            ],
            'discount_start_date' => ['sometimes', 'nullable', 'date'],
            'discount_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:discount_start_date'],
            'stock_quantity' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'stock_unit' => ['sometimes', Rule::in(['kg', 'pieces', 'units', 'liters', 'Kg', 'Pieces', 'Units', 'L'])],
            'enabled' => ['sometimes', 'boolean'],
            'product_type' => ['sometimes', Rule::in(['daily', 'standard'])],
            'variations' => ['sometimes', 'array', 'min:1'],
            'variations.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'variations.*.unit' => ['required', Rule::in(['gm', 'kg', 'ml', 'liter', 'piece', 'packet', 'dozen'])],
            'variations.*.price' => ['required', 'numeric', 'min:0'],
            'variations.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variations.*.sku' => ['nullable', 'string', 'max:100'],
            'variations.*.enabled' => ['sometimes', 'boolean'],
        ];
    }
}


