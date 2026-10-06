<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * What a member may change about their own profile: the public username,
 * once every 90 days (the first choice, replacing a temporary „clen-…“ name,
 * is not limited). The private name and the address are not editable here.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
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
            'username' => UsernameValidator::rules($this->member()),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->member();
                $next = $user->usernameChangeAvailableAt();

                if ($next === null || $validator->errors()->has('username') || $this->input('username') === $user->username) {
                    return;
                }

                $validator->errors()->add('username', __('api.username.cooldown', [
                    'date' => $next->format('j.n.Y'),
                ]));
            },
        ];
    }

    private function member(): User
    {
        /** @var User */
        return $this->user();
    }
}
