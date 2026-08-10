<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Deliberately NOT unique: rejecting a duplicate here tells an anonymous
            // caller that the address is registered. The controller handles the
            // collision and answers identically either way.
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
