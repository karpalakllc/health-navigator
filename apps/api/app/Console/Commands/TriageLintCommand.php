<?php

namespace App\Console\Commands;

use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowLinter;
use App\Services\Triage\V2\GlobalScreen;
use App\Services\Triage\V2\LintReport;
use Illuminate\Console\Command;

/**
 * Lints guidance flow files against docs/triage-flows.md without importing
 * anything. Exit code 1 on any error (and on warnings with --strict).
 */
class TriageLintCommand extends Command
{
    protected $signature = 'triage:lint
                            {paths?* : Flow files to check (default: every file in the flows directory)}
                            {--strict : Treat warnings as errors}';

    protected $description = 'Check symptom-guidance flow files (paths end in outcomes, red flags first, citations, populations, 194/112)';

    public function handle(FlowLinter $linter): int
    {
        $globalReport = new LintReport;
        $global = GlobalScreen::load(null, $globalReport);
        $linter->lintGlobal($global, $globalReport);
        $this->printReport('global', $globalReport);

        $errors = count($globalReport->errors);
        $warnings = count($globalReport->warnings);
        $paths = $this->argument('paths') ?: FlowImporter::flowFiles();

        foreach ($paths as $path) {
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $report = new LintReport;

            if (! is_array($decoded)) {
                $report->error(basename($path), 'not readable as JSON');
            } else {
                $report = $linter->lint($decoded, $global, basename($path, '.json'));
            }

            $this->printReport(basename($path), $report);
            $errors += count($report->errors);
            $warnings += count($report->warnings);
        }

        $this->newLine();
        $this->line(sprintf('%d file(s), %d error(s), %d warning(s).', count($paths), $errors, $warnings));

        return $errors > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function printReport(string $name, LintReport $report): void
    {
        if ($report->errors === [] && $report->warnings === []) {
            $this->line("<info>✓</info> {$name}");

            return;
        }

        $this->line(($report->ok() ? '<comment>!</comment>' : '<error>✗</error>')." {$name}");

        foreach ($report->errors as $error) {
            $this->line("    <error>error</error>   {$error}");
        }

        foreach ($report->warnings as $warning) {
            $this->line("    <comment>warning</comment> {$warning}");
        }
    }
}
