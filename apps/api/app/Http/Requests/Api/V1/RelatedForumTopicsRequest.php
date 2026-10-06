<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Forum\RelatedForumTopics;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Exactly one of doctor or facility (a profile slug).
 */
class RelatedForumTopicsRequest extends FormRequest
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
            'doctor' => ['required_without:facility', 'prohibits:facility', 'string', 'max:255'],
            'facility' => ['required_without:doctor', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.RelatedForumTopics::MAX_LIMIT],
        ];
    }
}
