<?php

namespace App\Services\Triage\V2;

use App\Models\TriageFlowReview;
use App\Models\TriageFlowVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clinician sign-off and publication of flow versions. A version goes public
 * only when it passes the linter (re-run against the current global screen)
 * and a staff member has recorded a clinician's approval of that version.
 */
final class FlowPublication
{
    public function __construct(
        private readonly FlowLinter $linter,
    ) {}

    /**
     * @param  array{decision: string, reviewer_name?: string|null, reviewer_registration?: string|null, reviewed_on: string, note: string}  $data
     */
    public function recordReview(TriageFlowVersion $version, array $data, ?User $staff): TriageFlowReview
    {
        if (! in_array($data['decision'], [TriageFlowReview::DECISION_APPROVED, TriageFlowReview::DECISION_CHANGES_REQUESTED], true)) {
            throw ValidationException::withMessages(['decision' => 'Unknown decision.']);
        }

        if (trim($data['note']) === '') {
            throw ValidationException::withMessages(['note' => 'A review note is required.']);
        }

        if ($data['decision'] === TriageFlowReview::DECISION_APPROVED) {
            foreach (['reviewer_name' => 'The clinician\'s name', 'reviewer_registration' => 'The clinician\'s registration / licence number'] as $field => $label) {
                if (self::blankToNull($data[$field] ?? null) === null) {
                    throw ValidationException::withMessages([$field => "{$label} is required to record an approval."]);
                }
            }
        }

        return DB::transaction(function () use ($version, $data, $staff): TriageFlowReview {
            $review = $version->reviews()->create([
                'decision' => $data['decision'],
                'reviewer_name' => self::blankToNull($data['reviewer_name'] ?? null),
                'reviewer_registration' => self::blankToNull($data['reviewer_registration'] ?? null),
                'reviewed_on' => $data['reviewed_on'],
                'note' => trim($data['note']),
                'recorded_by' => $staff?->id,
            ]);

            if (in_array($version->status, [TriageFlowVersion::STATUS_DRAFT, TriageFlowVersion::STATUS_REVIEWED], true)) {
                $version->update([
                    'status' => $data['decision'] === TriageFlowReview::DECISION_APPROVED
                        ? TriageFlowVersion::STATUS_REVIEWED
                        : TriageFlowVersion::STATUS_DRAFT,
                ]);
            }

            // A clinician who finds a problem in the live version takes it down at once.
            if ($version->isPublished() && $data['decision'] === TriageFlowReview::DECISION_CHANGES_REQUESTED) {
                $this->unpublish($version);
            }

            return $review;
        });
    }

    /**
     * Why the version cannot be published, or null when it can.
     */
    public function blocker(TriageFlowVersion $version, ?User $staff = null): ?string
    {
        if ($version->isPublished()) {
            return 'This version is already published.';
        }

        if ($version->status === TriageFlowVersion::STATUS_RETIRED && ! $version->isClinicallyApproved()) {
            return 'A retired version needs a new clinician review before it can be published again.';
        }

        if (! $version->isClinicallyApproved()) {
            return 'Record a clinician review (approved) for this version first.';
        }

        $recorder = ($version->relationLoaded('latestReview') ? $version->latestReview : $version->latestReview()->first())?->recorded_by;

        if ($staff !== null && $recorder !== null && $recorder === $staff->id) {
            return 'The staff member who recorded the clinician\'s approval cannot publish the same version. Ask a second staff member to publish it (the owner can create another staff account).';
        }

        $report = $this->lint($version);

        if (! $report->ok()) {
            return 'The flow has linter errors: '.implode(' | ', array_slice($report->errors, 0, 3));
        }

        return null;
    }

    public function publish(TriageFlowVersion $version, ?User $staff): void
    {
        $blocker = $this->blocker($version, $staff);

        if ($blocker !== null) {
            throw ValidationException::withMessages(['version' => $blocker]);
        }

        $report = $this->lint($version);

        DB::transaction(function () use ($version, $staff, $report): void {
            TriageFlowVersion::query()
                ->where('triage_flow_id', $version->triage_flow_id)
                ->where('status', TriageFlowVersion::STATUS_PUBLISHED)
                ->update(['status' => TriageFlowVersion::STATUS_RETIRED, 'retired_at' => Carbon::now()]);

            $version->update([
                'status' => TriageFlowVersion::STATUS_PUBLISHED,
                'published_at' => Carbon::now(),
                'published_by' => $staff?->id,
                'retired_at' => null,
                'lint_report' => $report->toArray(),
                'lint_errors' => count($report->errors),
                'lint_warnings' => count($report->warnings),
            ]);

            $version->flow()->update(['title' => $version->title()]);
        });
    }

    /** Takes the flow out of public guidance; sessions already running keep their pinned version. */
    public function unpublish(TriageFlowVersion $version): void
    {
        if (! $version->isPublished()) {
            return;
        }

        $version->update(['status' => TriageFlowVersion::STATUS_RETIRED, 'retired_at' => Carbon::now()]);
    }

    public function lint(TriageFlowVersion $version): LintReport
    {
        $report = new LintReport;
        $global = GlobalScreen::load(null, $report);
        $this->linter->lintGlobal($global, $report);
        $report->merge($this->linter->lint($version->definition, $global, $version->flow?->key));

        return $report;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
