<?php

namespace App\Validator;

use Illuminate\Validation\Rule;

class CustomerValidator extends BaseValidator
{
    public static function onCreate(): array
    {
        // Get validation country from config (default: AE for UAE)
        $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));

        return [
            'name' => array_merge(
                self::nameRules(true, 2, 100),
                ['regex:/^[a-zA-Z\s\-\']+$/']
            ),
            'whatsapp_number' => ['required', 'string', 'regex:' . $countryRules['regex'], 'unique:customers,whatsapp_number'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'remarks' => self::descriptionRules(),
            'status' => self::statusRules(),
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function onUpdate(?int $id = null): array
    {
        // Get validation country from config (default: AE for UAE)
        $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));

        return [
            'name' => array_merge(
                self::nameRules(false, 2, 100),
                ['regex:/^[a-zA-Z\s\-\']+$/']
            ),
            'whatsapp_number' => ['sometimes', 'required', 'string', 'regex:' . $countryRules['regex'], Rule::unique('customers', 'whatsapp_number')->ignore($id)],
            'landmark' => ['nullable', 'string', 'max:255'],
            'remarks' => self::descriptionRules(),
            'active' => ['sometimes', 'boolean'],
            // Always accept both address and area fields
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        $validationCountry = strtoupper(config('phone.validation_country', 'AE'));
        $countryRules = config("phone.rules.{$validationCountry}", config('phone.rules.AE'));

        return [
            'whatsapp_number.regex' => $countryRules['error_message'],
            'name.regex' => 'Please enter a valid name (2-100 characters, letters only)',
        ];
    }
}



