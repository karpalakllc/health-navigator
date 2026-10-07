<?php

namespace App\Console\Commands;

use App\Services\Triage\V2\FlowImporter;
use Illuminate\Console\Command;

/**
 * Loads guidance flow files as versioned drafts (docs/triage-flows.md).
 * Nothing becomes public here, except a grandfathered flow's first version:
 * publication needs a clinician review recorded in the admin panel.
 */
class TriageImportCommand extends Command
{
    protected $signature = 'triage:import
                            {paths?* : Flow files to import (default: every file in the flows directory)}
                            {--dry-run : Lint and report without writing}';

    protected $description = 'Import symptom-guidance flow files as draft versions (lints first; unchanged files are skipped)';

    public function handle(FlowImporter $importer): int
    {
        $result = $importer->import($this->argument('paths') ?: null, (bool) $this->option('dry-run'));

        foreach ($result['global']->errors as $error) {
            $this->error("global: {$error}");
        }

        $failed = ! $result['global']->ok();

        foreach ($result['files'] as $file) {
            $name = basename($file['path']);
            $version = $file['version'] !== null ? " v{$file['version']}" : '';

            $this->line(match ($file['result']) {
                'rejected', 'skipped' => "<error>{$file['result']}</error> {$name}",
                'unchanged' => "<comment>unchanged</comment> {$name}{$version}",
                default => "<info>{$file['result']}</info> {$name}{$version}",
            });

            foreach ($file['report']->errors as $error) {
                $this->line("    <error>error</error>   {$error}");
            }

            foreach ($file['report']->warnings as $warning) {
                $this->line("    <comment>warning</comment> {$warning}");
            }

            $failed = $failed || in_array($file['result'], ['rejected', 'skipped'], true);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
