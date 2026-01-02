<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirm Order Request
 * 
 * Validates order confirmation form submission.
 */
class ConfirmOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'min:3', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'whatsapp' => [
                'required',
                'regex:/^[0-9+\-\s]+$/',
                'regex:/^(\+91|91)?[6-9][0-9]{9}$|^(\+971|971)?[0-9]{9}$/'
            ],
            'email' => ['nullable', 'email'],
            'address' => [
                'required',
                'min:2',
                function ($attr, $value, $fail) {
                    if (!str_contains(strtolower($value), 'dubai')) {
                        $fail('Please select a valid Dubai address');
                    }
                }
            ],
            'grand_total' => ['required', 'numeric', 'min:1'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Please provide your name',
            'customer_name.regex' => 'Name should contain only letters',
            'whatsapp.required' => 'Please provide your WhatsApp number',
            'whatsapp.regex' => 'Only Indian (+91) and UAE (+971) numbers are allowed',
            'address.required' => 'Please provide your delivery address',
            'address.min' => 'Address must be at least 2 characters',
            'email.email' => 'Please provide a valid email address',
            'grand_total.required' => 'Grand total is required',
            'grand_total.numeric' => 'Grand total must be a valid number',
            'grand_total.min' => 'Grand total must be at least 1',
        ];
    }
}

