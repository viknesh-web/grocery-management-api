<?php

namespace App\Validator;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class OrderValidator
{
    /**
     * Validation rules for order confirmation.
     *
     * @return array
     */
    public static function onConfirm(): array
    {
        return [
            'customer_name' => ['required', 'min:3', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'whatsapp' => ['required', 'regex:/^[0-9+\-\s]+$/', 'regex:/^(\+91|91)?[6-9][0-9]{9}$|^(\+971|971)?[0-9]{9}$/'],
            'email' => 'nullable|email',
            'address' => [
                'required',
                'min:2',
                function ($attr, $value, $fail) {
                    if (!str_contains(strtolower($value), 'dubai')) {
                        $fail('Please select a valid Dubai address');
                    }
                }
            ],
            'grand_total' => 'required|numeric|min:1',
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array
     */
    public static function messages(): array
    {
        return [
            'customer_name.regex' => 'Name should contain only letters',
            'whatsapp.regex' => 'Only Indian (+91) and UAE (+971) numbers are allowed',
        ];
    }

    /**
     * Validate product quantities (including minimum quantity check).
     *
     * @param array $products Array of products with qty and unit
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateProductQuantities(array $products): array
    {
        $errors = [];

        foreach ($products as $productId => $productData) {
            if (!isset($productData['qty']) || $productData['qty'] <= 0) {
                continue; // Skip products with no quantity
            }

            $product = Product::find($productId);

            if (!$product) {
                $errors["product_{$productId}"] = "Product not found";
                continue;
            }

            $quantity = (float) $productData['qty'];
            $unit = $productData['unit'] ?? $product->stock_unit;

            // Validate minimum quantity
            if ($product->hasMinimumQuantity()) {
                if (!$product->meetsMinimumQuantity($quantity, $unit)) {
                    $errorMessage = $product->getMinimumQuantityError($quantity, $unit);
                    $errors["product_{$productId}"] = $errorMessage;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate single product quantity.
     *
     * @param Product $product
     * @param float $quantity
     * @param string $unit
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateSingleProductQuantity(Product $product, float $quantity, string $unit): array
    {
        if (!$product->hasMinimumQuantity()) {
            return ['valid' => true, 'error' => null];
        }

        if ($product->meetsMinimumQuantity($quantity, $unit)) {
            return ['valid' => true, 'error' => null];
        }

        return [
            'valid' => false,
            'error' => $product->getMinimumQuantityError($quantity, $unit),
        ];
    }

    /**
     * Get validation errors for display.
     *
     * @param array $errors
     * @return string
     */
    public static function formatErrors(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }

        $messages = [];
        foreach ($errors as $field => $error) {
            if (is_array($error)) {
                $messages[] = implode(', ', $error);
            } else {
                $messages[] = $error;
            }
        }

        return implode("\n", $messages);
    }
}