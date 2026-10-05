<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\EmailAddress;

/**
 * An address is one identity whatever case it was typed in. Normalising before
 * validation means every lookup the auth endpoints make — login, register,
 * resend, forgot and reset — compares like with like, on SQLite and on Postgres
 * (whose `=` is case-sensitive).
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => EmailAddress::normalize($email)]);
        }
    }
}
