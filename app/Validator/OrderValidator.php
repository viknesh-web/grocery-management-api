<?php

namespace App\Validator;

class OrderValidator
{
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

    public static function messages(): array
    {
        return [
            'customer_name.regex' => 'Name should contain only letters',
            'whatsapp.regex' => 'Only Indian (+91) and UAE (+971) numbers are allowed',
        ];
    }
}

