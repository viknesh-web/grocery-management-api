<?php

namespace App\Http\Requests\PriceUpdate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Price History Request
 * 
 * Validates request for getting price history.
 */
class PriceHistoryRequest extends FormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get limit from request.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->input('limit', 50);
    }
}

