<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Forum\ForumTagNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreForumTopicRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'body' => ['required', 'string', 'min:20', 'max:10000'],
            'accepted_community_rules' => ['required', 'accepted'],
            // Optional keyword suggestions; staff and moderators can change
            // them later in the admin panel.
            'tags' => ['nullable', 'array', 'max:'.ForumTagNormalizer::MAX_PER_TOPIC],
            'tags.*' => [
                'string',
                'max:'.ForumTagNormalizer::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ForumTagNormalizer::name($value) === null) {
                        $fail(__('api.forum.tag_invalid'));
                    }
                },
            ],
        ];
    }
}
