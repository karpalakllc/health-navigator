<?php

namespace App\Enums;

/**
 * What a public request about a directory profile asks for. The two follow
 * different legal routes (docs/legal/research-memo.md §2.1), so they carry
 * different deadlines and are resolved differently, but share one queue,
 * as do profile reports.
 */
enum ProfileCorrectionType: string
{
    /** A fact on the profile is wrong or out of date (ЗЗЛП чл. 20: 15 days). */
    case Correction = 'correction';

    /**
     * The listed doctor objects to the listing or asks for its removal
     * (ЗЗЛП чл. 25 / чл. 21: balancing test, reasoned answer within 30 days).
     */
    case Objection = 'objection';

    /**
     * „Пријави профил“ (W7-C): someone says the whole profile is wrong — fake,
     * the wrong person, no longer here, or inappropriate. Never hides the
     * profile by itself; staff decide.
     */
    case Report = 'report';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Days from receipt to the target answer date shown in the queue. */
    public function dueDays(): int
    {
        return match ($this) {
            self::Correction => 15,
            self::Objection => 30,
            self::Report => 7,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Correction',
            self::Objection => 'Objection / removal',
            self::Report => 'Profile report',
        };
    }

    /** As the web words it, for the staff email. */
    public function macedonianLabel(): string
    {
        return match ($this) {
            self::Correction => 'Грешка во профилот',
            self::Objection => 'Приговор или барање за отстранување',
            self::Report => 'Пријава на профил',
        };
    }
}
