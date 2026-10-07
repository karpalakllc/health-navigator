<?php

namespace App\Services\Triage\V2;

use App\Models\TriageFlow;
use App\Models\TriageFlowVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loads flow files (docs/triage-flows.md) into versioned definitions.
 *
 * - A file that fails the linter is not imported.
 * - An unchanged file (same canonical hash as the newest version) is a no-op.
 * - A changed file becomes a new **draft** version; whatever is published
 *   stays live until a newer version is reviewed and published.
 * - Grandfathered flows (config triage.grandfathered_flows) are published on
 *   their first import, so the guidance that worked before keeps working.
 */
final class FlowImporter
{
    public function __construct(
        private readonly FlowLinter $linter,
    ) {}

    /**
     * @param  list<string>|null  $paths  flow files; null = every file in the flows directory
     * @return array{global: LintReport, files: list<array{path: string, key: string|null, result: string, version: int|null, report: LintReport}>}
     */
    public function import(?array $paths = null, bool $dryRun = false): array
    {
        $globalReport = new LintReport;
        $global = GlobalScreen::load(null, $globalReport);
        (new FlowLinter)->lintGlobal($global, $globalReport);

        $paths ??= self::flowFiles();
        $files = [];

        foreach ($paths as $path) {
            $files[] = $globalReport->ok()
                ? $this->importFile($path, $global, $dryRun)
                : ['path' => $path, 'key' => null, 'result' => 'skipped', 'version' => null, 'report' => new LintReport];
        }

        return ['global' => $globalReport, 'files' => $files];
    }

    /** @return list<string> */
    public static function flowFiles(): array
    {
        $files = glob(rtrim((string) config('triage.flows_path'), '/').'/*.json') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array{path: string, key: string|null, result: string, version: int|null, report: LintReport}
     */
    public function importFile(string $path, GlobalScreen $global, bool $dryRun = false): array
    {
        $fileKey = basename($path, '.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded)) {
            $report = new LintReport;
            $report->error($fileKey, 'not readable as JSON');

            return ['path' => $path, 'key' => null, 'result' => 'rejected', 'version' => null, 'report' => $report];
        }

        $report = $this->linter->lint($decoded, $global, $fileKey);

        if (! $report->ok()) {
            return ['path' => $path, 'key' => $fileKey, 'result' => 'rejected', 'version' => null, 'report' => $report];
        }

        $hash = self::hash($decoded);

        /** @var TriageFlow|null $flow */
        $flow = TriageFlow::query()->v2()->where('key', $fileKey)->first();
        $latest = $flow?->versions()->first();

        if ($latest !== null && $latest->definition_hash === $hash) {
            return ['path' => $path, 'key' => $fileKey, 'result' => 'unchanged', 'version' => $latest->version, 'report' => $report];
        }

        if ($dryRun) {
            return ['path' => $path, 'key' => $fileKey, 'result' => 'would_import', 'version' => ($latest?->version ?? 0) + 1, 'report' => $report];
        }

        return DB::transaction(function () use ($flow, $fileKey, $decoded, $hash, $path, $report, $latest): array {
            $flow ??= TriageFlow::query()->create([
                'key' => $fileKey,
                'title' => (string) $decoded['title'],
                'is_published' => false,
            ]);

            $grandfathered = in_array($fileKey, (array) config('triage.grandfathered_flows', []), true)
                && ! $flow->versions()->exists();

            $version = TriageFlowVersion::query()->create([
                'triage_flow_id' => $flow->id,
                'version' => ($latest?->version ?? 0) + 1,
                'status' => $grandfathered ? TriageFlowVersion::STATUS_PUBLISHED : TriageFlowVersion::STATUS_DRAFT,
                'definition' => $decoded,
                'definition_hash' => $hash,
                'source_path' => self::relativePath($path),
                'lint_report' => $report->toArray(),
                'lint_errors' => count($report->errors),
                'lint_warnings' => count($report->warnings),
                'review_exempt_reason' => $grandfathered ? 'Live before clinician sign-off existed (v1 flow carried over).' : null,
                'published_at' => $grandfathered ? Carbon::now() : null,
            ]);

            if (! $flow->publishedVersion()->exists()) {
                // Keep the list title in step with the newest content.
                $flow->update(['title' => (string) $decoded['title']]);
            }

            return [
                'path' => $path,
                'key' => $fileKey,
                'result' => $grandfathered ? 'published' : 'imported',
                'version' => $version->version,
                'report' => $report,
            ];
        });
    }

    /**
     * Canonical hash: object keys sorted, lists kept in order, so
     * re-formatting a file does not create a new version.
     *
     * @param  array<mixed>  $definition
     */
    public static function hash(array $definition): string
    {
        return hash('sha256', (string) json_encode(self::canonical($definition), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    private static function relativePath(string $path): string
    {
        $base = base_path().'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
