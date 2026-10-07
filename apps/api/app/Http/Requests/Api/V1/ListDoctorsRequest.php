<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesSearchQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDoctorsRequest extends FormRequest
{
    use NormalizesSearchQuery;

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
            'specialty' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            // A slug from GET /languages. Unlike specialty, an unknown one is a
            // 422 rather than an empty page: the filter only ever offers known
            // slugs, so anything else is a stale or hand-made URL.
            'language' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('languages', 'slug')
                    ->where('is_published', true)
                    ->whereNull('deleted_at'),
            ],
            'q' => ['nullable', 'string', 'max:255'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:name,rating'],
            'min_reviews' => ['nullable', 'integer', 'min:0', 'max:100'],
            // „Само верификувани“.
            'verified' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
