<?php

namespace App\Enums;

use App\Models\Doctor;
use App\Models\Facility;

/**
 * Optional 1–5 sub-ratings a review can carry next to its required overall
 * stars. Doctors and facilities (pharmacies included) have their own four;
 * „waiting_time“ is shared. The codes are the API contract; the web labels
 * them.
 */
enum ReviewAspect: string
{
    case Communication = 'communication';
    case Explanation = 'explanation';
    case WaitingTime = 'waiting_time';
    case Respect = 'respect';
    case Cleanliness = 'cleanliness';
    case Organisation = 'organisation';
    case Staff = 'staff';

    /**
     * Below this many approved ratings an aspect's average is withheld: one or
     * two scores are noise, and an average of one is that member's score.
     */
    public const MIN_RATINGS_SHOWN = 3;

    /**
     * The aspects of a profile type, in display order.
     *
     * @return list<self>
     */
    public static function forReviewable(string $reviewableType): array
    {
        return match ($reviewableType) {
            Doctor::class => [self::Communication, self::Explanation, self::WaitingTime, self::Respect],
            Facility::class => [self::Cleanliness, self::Organisation, self::WaitingTime, self::Staff],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(string $reviewableType): array
    {
        return array_map(fn (self $aspect): string => $aspect->value, self::forReviewable($reviewableType));
    }
}
