<?php

namespace App\Enums;

/**
 * Why someone reported a whole profile („Пријави профил“). The codes are the
 * API contract; the web shows its own Macedonian labels, the admin panel the
 * English ones below.
 */
enum ProfileReportReason: string
{
    /** The profile is fake or the person or place does not exist. */
    case FakeProfile = 'fake_profile';

    /** The details belong to someone else (a namesake, a mix-up). */
    case WrongPerson = 'wrong_person';

    /** No longer works (or operates) at the listed place. */
    case NoLongerHere = 'no_longer_here';

    /** An inappropriate photo or text on the profile. */
    case InappropriateContent = 'inappropriate_content';

    case Other = 'other';

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
            self::FakeProfile => 'Fake or non-existent profile',
            self::WrongPerson => 'Wrong person',
            self::NoLongerHere => 'No longer works here',
            self::InappropriateContent => 'Inappropriate photo or content',
            self::Other => 'Other',
        };
    }
}
