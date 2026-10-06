<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'min:10', 'max:2000'],
            // Optional sub-ratings keyed by aspect code; which codes a profile
            // accepts depends on its type and is checked by the controller.
            'aspects' => ['sometimes', 'nullable', 'array'],
            'aspects.*' => ['nullable', 'integer', 'between:1,5'],
        ];
    }
}
