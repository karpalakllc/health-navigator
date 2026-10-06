<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesDisplayName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a member may change about their own profile. Only the public display
 * name for now; the private name and the address are not editable here.
 */
class UpdateProfileRequest extends FormRequest
{
    use ValidatesDisplayName;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDisplayName();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'display_name' => $this->displayNameRules(),
        ];
    }
}
