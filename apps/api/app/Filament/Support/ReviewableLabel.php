<?php

namespace App\Filament\Support;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

final class ReviewableLabel
{
    public static function forReview(Review $record): string
    {
        $reviewable = $record->reviewable;

        if (! $reviewable instanceof Model) {
            return '—';
        }

        return match ($record->reviewable_type) {
            Doctor::class => 'Doctor: '.$reviewable->full_name,
            Facility::class => $reviewable instanceof Facility && $reviewable->isPharmacy()
                ? 'Pharmacy: '.$reviewable->name
                : 'Facility: '.$reviewable->name,
            default => '—',
        };
    }

    public static function forReviewDetail(Review $record): string
    {
        $reviewable = $record->reviewable;

        if (! $reviewable instanceof Model) {
            return '—';
        }

        return match ($record->reviewable_type) {
            Doctor::class => 'Doctor: '.$reviewable->full_name.' ('.$reviewable->slug.')',
            Facility::class => $reviewable instanceof Facility && $reviewable->isPharmacy()
                ? 'Pharmacy: '.$reviewable->name.' ('.$reviewable->slug.')'
                : 'Facility: '.$reviewable->name.' ('.$reviewable->slug.')',
            default => '—',
        };
    }
}
