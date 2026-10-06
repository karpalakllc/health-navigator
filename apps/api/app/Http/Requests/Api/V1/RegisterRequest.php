<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesEmail;
use App\Http\Requests\Api\V1\Concerns\ValidatesDisplayName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesEmail {
        prepareForValidation as normalizeEmail;
    }
    use ValidatesDisplayName;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();
        $this->normalizeDisplayName();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Private: account page, admin, mail. Never shown publicly.
            'name' => ['required', 'string', 'max:255'],
            // Shown next to reviews and forum posts instead of the name.
            'display_name' => $this->displayNameRules(),
            // Deliberately NOT unique: rejecting a duplicate here tells an anonymous
            // caller that the address is registered. The controller handles the
            // collision and answers identically either way.
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
