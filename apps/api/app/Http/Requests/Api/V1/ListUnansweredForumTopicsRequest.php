<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesSearchQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListUnansweredForumTopicsRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'max:255'],
            // Optionally leave a brand-new question to its first readers.
            'min_age_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
