<?php

namespace App\Support\Licences;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The date of the latest Лекарска комора list we applied — the date the
 * Комора states on its page („изготвени заклучно со 2.7.2026“), stored by
 * `import:komora-licences` in the run's source_meta.list_date and on every
 * licence row it saw (komora_licences.list_date).
 *
 * Only a complete list counts: a partial `--file` import of some letters
 * says nothing about the rows it did not contain.
 */
final class KomoraListDate
{
    public static function latestApplied(): ?CarbonImmutable
    {
        $run = ImportRun::query()
            ->where('source', KomoraLicenceImporter::SOURCE)
            ->where('dry_run', false)
            ->where('status', ImportRunStatus::Succeeded)
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (ImportRun $run): bool => is_string($run->source_meta['list_date'] ?? null)
                && (bool) ($run->source_meta['complete_list'] ?? true));

        if ($run !== null) {
            return CarbonImmutable::parse((string) $run->source_meta['list_date'])->startOfDay();
        }

        // Licences staged before runs recorded their list date: the newest
        // date on the current list.
        $date = DB::table('komora_licences')->whereNull('missing_since')->max('list_date');

        return $date === null ? null : CarbonImmutable::parse((string) $date)->startOfDay();
    }
}
