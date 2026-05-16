<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesSearchQuery;
use App\Support\UnifiedSearch;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.UnifiedSearch::MAX_PER_VERTICAL],
        ];
    }
}
