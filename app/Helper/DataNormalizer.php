<?php

namespace App\Helper;

use Illuminate\Support\Str;

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

        if (isset($data['area'])) {
            $data['area'] = ($data['area'] !== null && $data['area'] !== '') 
                ? trim((string) $data['area']) 
                : null;
        }

        if (isset($data['landmark'])) {
            $data['landmark'] = trim((string) $data['landmark']);
        }

        if (isset($data['remarks'])) {
            $data['remarks'] = trim((string) $data['remarks']);
        }

        if (isset($data['active'])) {
            $data['active'] = (bool) $data['active'];
        } elseif ($customerId === null) {
            $data['active'] = true;
        }

        return $data;
    }

    public static function normalizeCategory(array $data, ?int $categoryId = null): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
            
            if (!isset($data['slug']) || empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }
        }

        if (isset($data['slug'])) {
            $data['slug'] = Str::slug(trim((string) $data['slug']));
        }

        if (isset($data['description'])) {
            $data['description'] = trim((string) $data['description']);
        }

        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($categoryId === null) {
            $data['is_active'] = true;
        }

        if (isset($data['display_order']) && $data['display_order'] !== null && $data['display_order'] !== '') {
            $data['display_order'] = (int) $data['display_order'];
        }

        if (isset($data['parent_id'])) {
            if ($data['parent_id'] !== null && $data['parent_id'] !== '') {
                $data['parent_id'] = (int) $data['parent_id'];
            } else {
                $data['parent_id'] = null;
            }
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

        if (isset($data['original_price'])) {
            $data['original_price'] = (float) $data['original_price'];
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

        if (isset($data['enabled'])) {
            $data['enabled'] = (bool) $data['enabled'];
        } elseif ($productId === null) {
            $data['enabled'] = true;
        }

        return $data;
    }
}

