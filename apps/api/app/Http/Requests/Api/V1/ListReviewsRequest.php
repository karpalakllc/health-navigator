<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'rating_high', 'rating_low'])],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
