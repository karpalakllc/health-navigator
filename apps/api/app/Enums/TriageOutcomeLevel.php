<?php

namespace App\Enums;

/**
 * Where a guidance outcome sends the visitor, most urgent first
 * (docs/triage-flows.md §8.1). With several symptoms the session's result is
 * the most urgent level any flow reached.
 */
enum TriageOutcomeLevel: string
{
    case EmergencyNow = 'emergency_now';
    case UrgentSameDay = 'urgent_same_day';
    case SeeDoctor24To48h = 'see_doctor_24_48h';
    case SeeGpThisWeek = 'see_gp_this_week';
    case PharmacyAdvice = 'pharmacy_advice';
    case SelfCare = 'self_care_with_safety_net';

    /** Higher is more urgent. */
    public function rank(): int
    {
        return match ($this) {
            self::EmergencyNow => 6,
            self::UrgentSameDay => 5,
            self::SeeDoctor24To48h => 4,
            self::SeeGpThisWeek => 3,
            self::PharmacyAdvice => 2,
            self::SelfCare => 1,
        };
    }

    public function isMoreUrgentThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
