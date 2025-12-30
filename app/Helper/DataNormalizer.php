<?php

namespace App\Helper;


class DataNormalizer
{
    public static function normalizeCustomer(array $data, ?int $customerId = null): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['whatsapp_number'])) {
            $whatsappNumber = trim((string) $data['whatsapp_number']);
            $whatsappNumber = preg_replace('/[\s\-\(\)]/', '', $whatsappNumber);
            
            $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
            $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
            $countryCode = $countryRules['country_code'];
            
            if (!str_starts_with($whatsappNumber, '+')) {
                if (str_starts_with($whatsappNumber, ltrim($countryCode, '+'))) {
                    $whatsappNumber = '+' . $whatsappNumber;
                } elseif (str_starts_with($whatsappNumber, '0')) {
                    $whatsappNumber = $countryCode . substr($whatsappNumber, 1);
                } else {
                    $whatsappNumber = $countryCode . $whatsappNumber;
                }
            }
            
            $data['whatsapp_number'] = $whatsappNumber;
        }

        if (isset($data['address'])) {
            $data['address'] = ($data['address'] !== null && $data['address'] !== '') 
                ? trim((string) $data['address']) 
                : null;
        }

        if (isset($data['landmark'])) {
            $data['landmark'] = trim((string) $data['landmark']);
        }

        if (isset($data['remarks'])) {
            $data['remarks'] = trim((string) $data['remarks']);
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['active', 'inactive'])) {
                $data['status'] = $customerId === null ? 'active' : 'inactive';
            }
        } elseif ($customerId === null) {
            $data['status'] = 'active';
        }

        return $data;
    }

    public static function normalizeCategory(array $data, ?int $categoryId = null): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['description'])) {
            $data['description'] = trim((string) $data['description']);
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['active', 'inactive'])) {
                $data['status'] = $categoryId === null ? 'active' : 'inactive';
            }
        } elseif ($categoryId === null) {
            $data['status'] = 'active';
        }

        return $data;
    }

    public static function normalizeProduct(array $data, ?int $productId = null): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['item_code'])) {
            $data['item_code'] = strtoupper(trim((string) $data['item_code']));
        }

        if (isset($data['regular_price'])) {
            $data['regular_price'] = (float) $data['regular_price'];
        }

        if (isset($data['discount_value'])) {
            $data['discount_value'] = $data['discount_value'] !== null && $data['discount_value'] !== '' 
                ? (float) $data['discount_value'] 
                : null;
        }

        if (isset($data['stock_quantity'])) {
            $data['stock_quantity'] = (float) $data['stock_quantity'];
        }

        if (isset($data['discount_type'])) {
            $data['discount_type'] = $data['discount_type'] ?? 'none';
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['active', 'inactive'])) {
                $data['status'] = $productId === null ? 'active' : 'inactive';
            }
        } elseif ($productId === null) {
            $data['status'] = 'active';
        }

        return $data;
    }
}

