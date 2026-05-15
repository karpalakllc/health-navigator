<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTriageAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.step_key' => ['required', 'string', 'max:64'],
            'answers.*.values' => ['present', 'array'],
            'answers.*.values.*' => ['required', 'string', 'max:64'],
        ];
    }
}
