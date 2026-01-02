<?php

namespace App\Http\Requests\PriceUpdate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recent Price Updates Request
 * 
 * Validates request for getting recent price updates.
 */
class RecentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

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
     * Get limit from request.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->input('limit', 20);
    }
}

