<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesEmail;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesEmail {
        prepareForValidation as normalizeEmail;
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEmail();

        if (is_string($this->input('username'))) {
            $this->merge(['username' => UsernameNormalizer::prepare($this->input('username'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Private: account page, admin, mail. Never shown publicly.
            'name' => ['required', 'string', 'max:255'],
            // Public and unique: shown next to reviews and forum posts instead
            // of the name. Unlike the address, a taken username may be
            // reported as taken — usernames are public anyway.
            'username' => UsernameValidator::rules(),
            // „I am at least 14 and accept the Terms of Use and the Privacy
            // Policy“: recorded as users.terms_accepted_at + terms_version.
            'accept_terms' => ['accepted'],
            // Deliberately NOT unique: rejecting a duplicate here tells an anonymous
            // caller that the address is registered. The controller handles the
            // collision and answers identically either way.
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
