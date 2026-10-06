<?php

namespace App\Enums;

/**
 * Which list a username term belongs to. Both refuse a username the same way
 * and with the same message (the member is never told which list matched);
 * the split is for staff, who curate them for different reasons.
 */
enum UsernameTermKind: string
{
    /** Profanity, sexual terms, slurs, hate, drugs, scams. */
    case Blocked = 'blocked';

    /** Names that would impersonate staff, the platform, a doctor or an authority, and site routes. */
    case Reserved = 'reserved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Blocked',
            self::Reserved => 'Reserved',
        };
    }
}
