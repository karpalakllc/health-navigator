<?php

namespace App\Support\Verification;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads the licence grace from a doctor's internal verification evidence
 * (`licence_grace: {expired_on, list_date, grace_until}`, written by the
 * engine's DoctorRules): the Комора licence expired after the date of the
 * list it came from, ФЗОМ still lists the doctor, and no newer list has been
 * imported. Admin only — the public site never says anything about expiry.
 */
final class LicenceGrace
{
    /**
     * @param  array<string, mixed>|null  $reasons  verification_reasons
     * @return array{expired_on: string|null, list_date: string|null, grace_until: string|null}|null
     */
    public static function of(?array $reasons): ?array
    {
        if (($reasons['status'] ?? null) !== VerificationStatus::Verified->value) {
            return null;
        }

        $grace = $reasons['evidence'][0]['licence_grace'] ?? null;

        if (! is_array($grace)) {
            return null;
        }

        return [
            'expired_on' => is_string($grace['expired_on'] ?? null) ? $grace['expired_on'] : null,
            'list_date' => is_string($grace['list_date'] ?? null) ? $grace['list_date'] : null,
            'grace_until' => is_string($grace['grace_until'] ?? null) ? $grace['grace_until'] : null,
        ];
    }

    /**
     * „Licence expired on 20.10.2026, after the latest Комора list
     * (02.07.2026); awaiting a newer list — kept verified (grace until
     * 18.04.2027).“ Null when the profile is not in a grace period.
     *
     * @param  array<string, mixed>|null  $reasons  verification_reasons
     */
    public static function adminNote(?array $reasons): ?string
    {
        $grace = self::of($reasons);

        if ($grace === null) {
            return null;
        }

        $expired = self::date($grace['expired_on']);

        return sprintf(
            'Licence expired%s after the latest Комора list (%s); awaiting a newer list — kept verified (grace until %s).',
            $expired !== null ? ' on '.$expired.',' : '',
            self::date($grace['list_date']) ?? 'unknown date',
            self::date($grace['grace_until']) ?? '?',
        );
    }

    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('d.m.Y');
        } catch (Throwable) {
            return null;
        }
    }
}
