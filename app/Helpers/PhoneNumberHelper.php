<?php

namespace App\Helpers;

/**
 * Phone Number Helper
 * 
 * Provides methods for normalizing, validating, and formatting phone numbers for WhatsApp.
 */
class PhoneNumberHelper
{
    /**
     * Normalize a phone number for WhatsApp.
     * 
     * Removes formatting, ensures proper country code, and formats for WhatsApp.
     *
     * @param string|null $phoneNumber
     * @param string|null $defaultCountryCode Optional country code to use if not present
     * @return string|null Normalized phone number with country code (e.g., +971501234567)
     */
    public static function normalize(?string $phoneNumber, ?string $defaultCountryCode = null): ?string
    {
        if (empty($phoneNumber)) {
            return null;
        }

        // Remove all whitespace, dashes, parentheses, and other formatting
        $cleaned = preg_replace('/[\s\-\(\)]/', '', trim($phoneNumber));

        // Get validation country from config (default: AE for UAE)
        $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
        $countryCode = $defaultCountryCode ?? $countryRules['country_code'];

        // If already starts with +, ensure it's properly formatted
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // If starts with country code without +, add +
        if (str_starts_with($cleaned, ltrim($countryCode, '+'))) {
            return '+' . $cleaned;
        }

        // If starts with 0, remove leading 0 and add country code
        if (str_starts_with($cleaned, '0')) {
            return $countryCode . substr($cleaned, 1);
        }

        // If no country code prefix, add country code
        return $countryCode . $cleaned;
    }

    /**
     * Format phone number for WhatsApp API (adds 'whatsapp:' prefix).
     *
     * @param string|null $phoneNumber
     * @return string|null Formatted number (e.g., whatsapp:+971501234567)
     */
    public static function formatForWhatsApp(?string $phoneNumber): ?string
    {
        if (empty($phoneNumber)) {
            return null;
        }

        // Remove 'whatsapp:' prefix if already present
        $cleaned = str_replace('whatsapp:', '', $phoneNumber);

        // Normalize the number first
        $normalized = self::normalize($cleaned);

        if (empty($normalized)) {
            return null;
        }

        // Add whatsapp: prefix
        return 'whatsapp:' . $normalized;
    }

    /**
     * Validate phone number format.
     *
     * @param string|null $phoneNumber
     * @return bool
     */
    public static function validate(?string $phoneNumber): bool
    {
        if (empty($phoneNumber)) {
            return false;
        }

        // Remove whatsapp: prefix if present
        $cleaned = str_replace('whatsapp:', '', $phoneNumber);

        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $cleaned);

        // Check if it starts with + and has valid format (E.164 format)
        // E.164: + followed by 1-15 digits
        return preg_match('/^\+[1-9]\d{1,14}$/', $cleaned) === 1;
    }

    /**
     * Get validation regex pattern for a country.
     *
     * @param string|null $countryCode Optional country code (defaults to config)
     * @return string Regex pattern
     */
    public static function getValidationRegex(?string $countryCode = null): string
    {
        $validationCountry = strtoupper($countryCode ?? config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
        
        return $countryRules['regex'] ?? '/^\+[1-9]\d{1,14}$/';
    }

    /**
     * Get validation error message for a country.
     *
     * @param string|null $countryCode Optional country code (defaults to config)
     * @return string Error message
     */
    public static function getValidationErrorMessage(?string $countryCode = null): string
    {
        $validationCountry = strtoupper($countryCode ?? config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));
        
        return $countryRules['error_message'] ?? 'Please enter a valid phone number in international format (e.g., +971501234567)';
    }

    /**
     * Extract country code from phone number.
     *
     * @param string $phoneNumber
     * @return string|null Country code (e.g., +971) or null if not found
     */
    public static function extractCountryCode(string $phoneNumber): ?string
    {
        if (preg_match('/^\+(\d{1,3})/', $phoneNumber, $matches)) {
            return '+' . $matches[1];
        }

        return null;
    }

    /**
     * Get phone number without country code.
     *
     * @param string $phoneNumber
     * @return string Phone number without country code
     */
    public static function withoutCountryCode(string $phoneNumber): string
    {
        // Remove whatsapp: prefix if present
        $cleaned = str_replace('whatsapp:', '', $phoneNumber);

        // Remove + and country code (assuming 1-3 digit country code)
        return preg_replace('/^\+\d{1,3}/', '', $cleaned);
    }
}

