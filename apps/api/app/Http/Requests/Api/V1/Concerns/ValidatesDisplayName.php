<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\DisplayName;
use Closure;

/**
 * The public display name: trimmed and whitespace-collapsed before the rules
 * run, then letters, spaces and . - ' only, at least two letters, no word
 * mixing Cyrillic and Latin, and no claimed title or role (DisplayName::
 * rejection()). Deliberately NOT unique — two
 * "Марија К." are expected, and a uniqueness check would let anyone probe
 * which names are taken.
 */
trait ValidatesDisplayName
{
    protected function normalizeDisplayName(): void
    {
        if (is_string($this->input('display_name'))) {
            $this->merge(['display_name' => DisplayName::normalize($this->input('display_name'))]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function displayNameRules(): array
    {
        return [
            'required',
            'string',
            'max:'.DisplayName::MAX_LENGTH,
            'regex:'.DisplayName::PATTERN,
            function (string $attribute, mixed $value, Closure $fail): void {
                $reason = is_string($value) ? DisplayName::rejection($value) : null;

                if ($reason !== null) {
                    $fail("validation.custom.display_name.{$reason}")->translate();
                }
            },
        ];
    }
}
