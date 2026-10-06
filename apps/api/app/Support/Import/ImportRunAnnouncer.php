<?php

namespace App\Support\Import;

use App\Enums\ImportRunStatus;
use App\Events\ImportRunFinished;
use App\Filament\Resources\ImportRuns\ImportRunResource;
use App\Models\ImportRun;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Turns a finished source import run into ImportRunFinished, the event the
 * data-ops alerts listen to (docs/data-import.md §10). Only real runs of
 * the scheduled sources count: a dry run, a run the source answered with
 * "not modified", a run still going and a hand-run website import are not
 * announced.
 *
 * Counts only — the event's contract forbids names and numbers.
 */
final class ImportRunAnnouncer
{
    public const SOURCES = ['fzom', 'komora'];

    public function announce(ImportRun $run): ?ImportRunFinished
    {
        if ($run->dry_run
            || ! in_array($run->source, self::SOURCES, true)
            || ! in_array($run->status, [ImportRunStatus::Succeeded, ImportRunStatus::Failed], true)) {
            return null;
        }

        $event = $run->source === 'komora' ? $this->komora($run) : $this->register($run);
        event($event);

        return $event;
    }

    /**
     * ФЗОМ (the generic ImportRunner counts): records seen are the doctors
     * and facilities in the snapshot; changes are profiles created, published
     * profiles changed and profiles found missing.
     */
    private function register(ImportRun $run): ImportRunFinished
    {
        return new ImportRunFinished(
            source: $run->source,
            succeeded: $run->status === ImportRunStatus::Succeeded,
            seen: $run->count('doctors_in_source') + $run->count('facilities_in_source'),
            created: $run->count('doctors_created') + $run->count('dentists_created') + $run->count('facilities_created'),
            updated: $run->count('review_changed'),
            missing: $run->count('review_missing'),
            conflicts: $run->count('review_conflict'),
            unmatched: $run->count('review_unmatched'),
            runId: $run->getKey(),
            error: $run->error,
            reviewUrl: $this->url($run),
        );
    }

    /**
     * Лекарска комора (KomoraLicenceImporter counts): records seen are the
     * distinct licences read; a licence newly attached counts as created,
     * one that left a complete list as missing; rows parked for staff are
     * unmatched.
     */
    private function komora(ImportRun $run): ImportRunFinished
    {
        return new ImportRunFinished(
            source: 'komora',
            succeeded: $run->status === ImportRunStatus::Succeeded,
            seen: max(0, $run->count('rows_parsed') - $run->count('duplicate_numbers')),
            created: $run->count('attached'),
            missing: $run->count('missing_from_list'),
            conflicts: $run->count('conflict'),
            unmatched: $run->count('ambiguous') + $run->count('no_match') + $run->count('specialty_mismatch') + $run->count('doctor_not_found'),
            runId: $run->getKey(),
            error: $run->error,
            reviewUrl: $this->url($run),
        );
    }

    private function url(ImportRun $run): ?string
    {
        try {
            return URL::to(ImportRunResource::getUrl('view', ['record' => $run]));
        } catch (Throwable) {
            return null;
        }
    }
}
