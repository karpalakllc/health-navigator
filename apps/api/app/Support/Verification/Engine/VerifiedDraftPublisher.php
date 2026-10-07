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
 * queue („Објави ги сите верификувани“), or automatically right after a run
 * when IMPORT_AUTO_PUBLISH_VERIFIED is on.
 *
 * A second set (owner's decision): doctor drafts current in ФЗОМ with no
 * licence on the Комора list (engine reason fzom_no_licence) and no other
 * open review item on them are published but stay „Неверификуван“ — in bulk
 * („Објави ги и неверификуваните од ФЗОМ“) or with
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
     * Open "new" items whose profile is verified and still a draft that was
     * never published (a profile staff unpublished stays so: their decision),
     * with no other open review item on it (possible duplicate, conflict,
     * missing, uncertain…) and not marked by the importer as having no
     * specialty (its "check they are doctors" warning needs a person).
     *
     * @return Builder<ImportReviewItem>
     */
    public function pending(): Builder
    {
        return $this->drafts()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
                    ->whereIn('subject_id', self::neverPublished(Doctor::query())->select('id')->whereNotNull('verified_at'))
                    ->whereNotIn('subject_id', self::blocked(FieldProvenance::SUBJECT_DOCTOR)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
                    ->whereIn('subject_id', self::neverPublished(Facility::query())->select('id')->whereNotNull('verified_at'))
                    ->whereNotIn('subject_id', self::blocked(FieldProvenance::SUBJECT_FACILITY))));
    }

    /**
     * Open "new" items, without the importer's no-specialty drafts.
     *
     * @return Builder<ImportReviewItem>
     */
    private function drafts(): Builder
    {
        return ImportReviewItem::query()->open()
            ->where('kind', ImportReviewKind::New)
            ->where(fn (Builder $query) => $query
                ->whereNull('details->no_specialty')
                ->orWhere('details->no_specialty', false));
    }

    /**
     * Subjects of this type with any other open review item.
     *
     * @return Builder<ImportReviewItem>
     */
    private static function blocked(string $subjectType): Builder
    {
        return ImportReviewItem::query()->open()
            ->where('kind', '!=', ImportReviewKind::New->value)
            ->where('subject_type', $subjectType)
            ->whereNotNull('subject_id')
            ->select('subject_id');
    }

    /**
     * Hidden and never published: published_at is stamped on every publish
     * (HasImportDraftLifecycle) and kept when a profile is unpublished.
     *
     * @template TModel of Doctor|Facility
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function neverPublished(Builder $query): Builder
    {
        return $query->where('is_published', false)->whereNull('published_at');
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

    /**
     * @param  int|null  $upToId  the highest item id the owner saw counted (the modal's snapshot)
     */
    public function publishAll(?User $by, ?int $upToId = null): int
    {
        return $this->publishItems($this->pending()->when($upToId !== null, fn (Builder $query) => $query->where('id', '<=', $upToId))->orderBy('id')->pluck('id')->all(), $by);
    }

    /**
     * Open "new" items of never-published doctor drafts the engine left
     * unverified only for want of a licence while ФЗОМ lists them today —
     * never a staff decision (verification_source auto) — with no other open
     * review item (conflict, missing, possible duplicate, uncertain…) on the
     * same doctor.
     *
     * @return Builder<ImportReviewItem>
     */
    public function pendingFzomUnverified(): Builder
    {
        return $this->drafts()
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->whereIn('subject_id', self::neverPublished(Doctor::query())->select('id')
                ->whereNull('verified_at')
                ->where('verification_source', VerificationSource::Auto->value)
                ->where('verification_reasons->reason', Reason::FZOM_NO_LICENCE))
            ->whereNotIn('subject_id', self::blocked(FieldProvenance::SUBJECT_DOCTOR));
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

    /**
     * @param  int|null  $upToId  the highest item id the owner saw counted (the modal's snapshot)
     */
    public function publishAllFzomUnverified(?User $by, ?int $upToId = null): int
    {
        return $this->publishItems($this->pendingFzomUnverified()->when($upToId !== null, fn (Builder $query) => $query->where('id', '<=', $upToId))->orderBy('id')->pluck('id')->all(), $by);
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
