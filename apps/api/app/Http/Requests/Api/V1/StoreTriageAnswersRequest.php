<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTriageAnswersRequest extends FormRequest
{
    public const MAX_ANSWERS = 30;

    public const MAX_VALUES_PER_ANSWER = 50;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Each answer costs queries (an upsert per step), so bound the
            // payload: a flow has a handful of steps plus red_flags, each with a
            // handful of options. Generous caps, far below anything abusive.
            'answers' => ['required', 'array', 'min:1', 'max:'.self::MAX_ANSWERS],
            'answers.*.step_key' => ['required', 'string', 'max:64', 'distinct'],
            'answers.*.values' => ['present', 'array', 'max:'.self::MAX_VALUES_PER_ANSWER],
            'answers.*.values.*' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }
}
