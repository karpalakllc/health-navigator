<?php

namespace App\Support\Verification\Engine;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\ImportReviewActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Publishes imported drafts the engine verified: in bulk from the review
 * queue („Објави ги сите верифицирани“), or automatically right after a run
 * when IMPORT_AUTO_PUBLISH_VERIFIED is on. Every publication goes through
 * ImportReviewActions::publish (suppressed doctors refused, hidden imported
 * specialties published along, the "new" item closed).
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
