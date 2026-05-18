<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateForumTopicModerationRequest extends FormRequest
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
            'is_pinned' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('is_pinned') && ! $this->has('is_locked')) {
                $validator->errors()->add('is_pinned', 'At least one moderation field is required.');
            }
        });
    }
}
