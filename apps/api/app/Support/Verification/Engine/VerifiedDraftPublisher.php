<?php

namespace App\Support\Verification\Engine;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\ImportReviewActions;
use App\Support\Verification\VerificationSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Publishes imported drafts the engine verified: in bulk from the review
 * queue („Објави ги сите верифицирани“), or automatically right after a run
 * when IMPORT_AUTO_PUBLISH_VERIFIED is on.
 *
 * A second set (owner's decision): doctor drafts current in ФЗОМ with no
 * licence on the Комора list (engine reason fzom_no_licence) and no other
 * open review item on them are published but stay „Неверифициран“ — in bulk
 * („Објави ги и неверифицираните од ФЗОМ“) or with
 * IMPORT_AUTO_PUBLISH_FZOM_UNVERIFIED. Ambiguous names, disagreeing sources,
 * specialty mismatches and website-only drafts never enter it.
 *
 * Every publication goes through ImportReviewActions::publish (suppressed
 * doctors refused, hidden imported specialties published along, the "new"
 * item closed).
 */
final class VerifiedDraftPublisher
{
    public function __construct(private readonly ImportReviewActions $actions) {}

    /**
     * Open "new" items whose profile is verified and still a hidden draft.
     *
     * @return Builder<ImportReviewItem>
     */
    public function pending(): Builder
    {
        return ImportReviewItem::query()->open()
            ->where('kind', ImportReviewKind::New)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
                    ->whereIn('subject_id', Doctor::query()->select('id')->whereNotNull('verified_at')->where('is_published', false)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
                    ->whereIn('subject_id', Facility::query()->select('id')->whereNotNull('verified_at')->where('is_published', false))));
    }

    public function count(): int
    {
        return $this->pending()->count();
    }

    /**
     * A random handful to glance at before publishing them all.
     *
     * @return Collection<int, ImportReviewItem>
     */
    public function sample(int $size = 20): Collection
    {
        return $this->pending()->inRandomOrder()->limit($size)->get();
    }

    public function publishAll(?User $by): int
    {
        return $this->publishItems($this->pending()->orderBy('id')->pluck('id')->all(), $by);
    }

    /**
     * Open "new" items of doctor drafts the engine left unverified only for
     * want of a licence while ФЗОМ lists them today — never a staff decision
     * (verification_source auto) — with no other open review item (conflict,
     * missing, possible duplicate, uncertain…) on the same doctor.
     *
     * @return Builder<ImportReviewItem>
     */
    public function pendingFzomUnverified(): Builder
    {
        $blocked = ImportReviewItem::query()->open()
            ->where('kind', '!=', ImportReviewKind::New->value)
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->whereNotNull('subject_id')
            ->select('subject_id');

        return ImportReviewItem::query()->open()
            ->where('kind', ImportReviewKind::New)
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->whereIn('subject_id', Doctor::query()->select('id')
                ->whereNull('verified_at')
                ->where('is_published', false)
                ->where('verification_source', VerificationSource::Auto->value)
                ->where('verification_reasons->reason', Reason::FZOM_NO_LICENCE))
            ->whereNotIn('subject_id', $blocked);
    }

    public function countFzomUnverified(): int
    {
        return $this->pendingFzomUnverified()->count();
    }

    /**
     * @return Collection<int, ImportReviewItem>
     */
    public function sampleFzomUnverified(int $size = 20): Collection
    {
        return $this->pendingFzomUnverified()->inRandomOrder()->limit($size)->get();
    }

    public function publishAllFzomUnverified(?User $by): int
    {
        return $this->publishItems($this->pendingFzomUnverified()->orderBy('id')->pluck('id')->all(), $by);
    }

    /**
     * Auto-publish (IMPORT_AUTO_PUBLISH_FZOM_UNVERIFIED): only the doctors
     * that entered the set in this run.
     *
     * @param  list<int>  $doctorIds
     */
    public function publishNewlyFzomUnverified(array $doctorIds): int
    {
        $ids = [];

        foreach (array_chunk($doctorIds, 500) as $chunk) {
            $ids = [...$ids, ...$this->pendingFzomUnverified()->whereIn('subject_id', $chunk)->pluck('id')->all()];
        }

        return $this->publishItems($ids, null);
    }

    /**
     * Auto-publish: only the drafts verified in this run.
     *
     * @param  list<int>  $doctorIds
     * @param  list<int>  $facilityIds
     */
    public function publishNewlyVerified(array $doctorIds, array $facilityIds): int
    {
        $ids = [];

        foreach ([FieldProvenance::SUBJECT_DOCTOR => $doctorIds, FieldProvenance::SUBJECT_FACILITY => $facilityIds] as $type => $subjects) {
            foreach (array_chunk($subjects, 500) as $chunk) {
                $ids = [...$ids, ...$this->pending()->where('subject_type', $type)->whereIn('subject_id', $chunk)->pluck('id')->all()];
            }
        }

        return $this->publishItems($ids, null);
    }

    /**
     * @param  list<int|string>  $itemIds
     */
    private function publishItems(array $itemIds, ?User $by): int
    {
        $published = 0;

        foreach (array_chunk($itemIds, 200) as $chunk) {
            foreach (ImportReviewItem::query()->whereKey($chunk)->open()->get() as $item) {
                if ($this->actions->publish($item, $by)) {
                    $published++;
                }
            }
        }

        return $published;
    }
}
