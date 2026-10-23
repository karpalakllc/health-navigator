<?php

namespace App\Console\Commands;

use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\SourceRecord;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Models\User;
use App\Support\Import\Fzom\FzomAssociates;
use App\Support\Import\Fzom\FzomImporter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * One-off clean-up after the health-associate rule (docs/data-import.md §3a):
 * profiles an earlier ФЗОМ import created for health associates (facsimile
 * 8xxxxx, no Комора licence) are not doctors. They stay hidden; this closes
 * their open review items („new“, „missing“, ...) so they leave the publish
 * queue, attributed to the staff member. The importer itself never queues
 * them as „missing“ (FzomImporter::markMissing), so this is tidying, not a
 * precondition. Idempotent: a second run finds nothing open.
 *
 * Never touched: a profile with a licence, with reviews, with an owner
 * account, a published one, one with a physician-only specialty (the
 * confirm-me exception), or one a source other than ФЗОМ created.
 */
class ImportFzomExcludeAssociatesCommand extends Command
{
    public const RESOLUTION = 'associate_excluded';

    protected $signature = 'import:fzom-exclude-associates
        {--by= : Email of the staff member (required to apply; recorded on every closed item)}
        {--dry-run : Count what would be closed, change nothing}';

    protected $description = 'Close the review items of ФЗОМ-created health-associate profiles (facsimile 8xxxxx, no licence); counts only';

    public function handle(): int
    {
        $prefixes = FzomAssociates::prefixes();

        if ($prefixes === []) {
            $this->line('No associate facsimile prefixes configured (import.fzom.associate_facsimile_prefixes); nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $by = null;

        if (! $dryRun) {
            $by = is_string($this->option('by')) ? User::query()->where('email', $this->option('by'))->first() : null;

            if ($by === null || ! $by->can('imports.manage')) {
                $this->error('Pass --by=<email> of a staff member who may manage imports (imports.manage), or use --dry-run.');

                return self::FAILURE;
            }
        }

        $profiles = 0;
        $skipped = ['licence' => 0, 'reviews' => 0, 'owner' => 0, 'published' => 0, 'physician_specialty' => 0];
        $closed = 0;

        $this->candidates()->withCount('reviews')->chunkById(500, function ($doctors) use ($dryRun, $by, &$profiles, &$skipped, &$closed): void {
            foreach ($doctors as $doctor) {
                /** @var Doctor $doctor */
                $reason = match (true) {
                    $doctor->licence_number !== null => 'licence',
                    $doctor->reviews_count > 0 => 'reviews',
                    $doctor->owner_user_id !== null => 'owner',
                    (bool) $doctor->is_published => 'published',
                    $this->ownPhysicianSpecialty($doctor) => 'physician_specialty',
                    default => null,
                };

                if ($reason !== null) {
                    $skipped[$reason]++;

                    continue;
                }

                $profiles++;
                $open = ImportReviewItem::query()->open()
                    ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->where('subject_id', $doctor->getKey())
                    ->get();

                foreach ($open as $item) {
                    if ($dryRun || $item->resolve(ImportReviewStatus::Dismissed, self::RESOLUTION, $by)) {
                        $closed++;
                    }
                }
            }
        });

        $this->line(sprintf(
            '%s %d open review items on %d associate profiles. Left alone: %d with a licence, %d with reviews, %d with an owner, %d published, %d with a physician specialty.',
            $dryRun ? 'Would close' : 'Closed',
            $closed,
            $profiles,
            $skipped['licence'],
            $skipped['reviews'],
            $skipped['owner'],
            $skipped['published'],
            $skipped['physician_specialty'],
        ));

        return self::SUCCESS;
    }

    /**
     * Does one of the person's OWN ФЗОМ specialty wordings (as last imported)
     * map to a physician-only specialty? The specialties stored on the
     * profile are no evidence: many came from the unit's activity or the
     * contract type, which describe the unit, not the person.
     */
    private function ownPhysicianSpecialty(Doctor $doctor): bool
    {
        $payload = SourceRecord::query()->where('source', FzomImporter::SOURCE)
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->where('subject_id', $doctor->getKey())
            ->value('payload');
        $keys = [];

        foreach ((array) (is_array($payload) ? ($payload['contracts'] ?? []) : []) as $contract) {
            foreach (explode(',', (string) ($contract['specialties'] ?? '')) as $wording) {
                if (trim($wording) !== '') {
                    $keys[] = SpecialtyAlias::keyFor($wording);
                }
            }
        }

        if ($keys === []) {
            return false;
        }

        return Specialty::withTrashed()
            ->whereIn('id', SpecialtyAlias::query()->where('source', FzomImporter::SOURCE)->whereIn('raw_key', array_unique($keys))->whereNotNull('specialty_id')->select('specialty_id'))
            ->pluck('slug')
            ->contains(fn ($slug): bool => FzomAssociates::isPhysicianOnlySlug((string) $slug));
    }

    /**
     * @return Builder<Doctor>
     */
    private function candidates(): Builder
    {
        return Doctor::query()
            ->where('import_source', 'fzom')
            ->whereNotNull('fzo_facsimile')
            ->where(function ($query): void {
                foreach (FzomAssociates::prefixes() as $prefix) {
                    $query->orWhere('fzo_facsimile', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%');
                }
            });
    }
}
