<?php

namespace App\Services\Triage\V2;

/**
 * The global red-flag screen and shared outcomes (docs/triage-flows.md §11),
 * merged from every file in the global directory, in file-name order.
 */
final class GlobalScreen
{
    public const SCHEMA = 'zdravje.triage.global/1';

    /**
     * @param  list<array<string, mixed>>  $redFlags
     * @param  array<string, array<string, mixed>>  $outcomes
     * @param  array<string, array<string, mixed>>  $sources
     */
    public function __construct(
        public readonly array $redFlags,
        public readonly array $outcomes,
        public readonly array $sources,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $files  file name => decoded JSON
     */
    public static function fromFiles(array $files, ?LintReport $report = null): self
    {
        ksort($files);
        $redFlags = [];
        $outcomes = [];
        $sources = [];
        $patches = [];

        foreach ($files as $name => $file) {
            if (($file['schema'] ?? null) !== self::SCHEMA) {
                $report?->error("global/{$name}", 'schema must be "'.self::SCHEMA.'"');
            }

            foreach ($file['red_flags'] ?? [] as $flag) {
                $redFlags[] = (array) $flag;
            }

            foreach ($file['outcomes'] ?? [] as $id => $outcome) {
                if (isset($outcomes[$id])) {
                    $report?->error("global/{$name}", "outcome \"{$id}\" is defined twice");
                }

                $outcomes[$id] = (array) $outcome;
            }

            foreach ($file['sources'] ?? [] as $source) {
                if (is_array($source) && isset($source['id']) && is_string($source['id'])) {
                    $sources[$source['id']] = $source;
                }
            }

            foreach ($file['outcome_patches'] ?? [] as $id => $patch) {
                $patches[] = [$name, $id, (array) $patch];
            }
        }

        foreach ($patches as [$name, $id, $patch]) {
            if (! isset($outcomes[$id])) {
                $report?->error("global/{$name}", "outcome_patches names unknown outcome \"{$id}\"");

                continue;
            }

            foreach (['call', 'do_now', 'watch_for', 'reasons'] as $field) {
                if (isset($patch[$field]) && is_array($patch[$field])) {
                    $outcomes[$id][$field] = array_values(array_merge($outcomes[$id][$field] ?? [], $patch[$field]));
                }
            }
        }

        return new self($redFlags, $outcomes, $sources);
    }

    public static function load(?string $directory = null, ?LintReport $report = null): self
    {
        $directory ??= (string) config('triage.global_path');
        $files = [];

        foreach (glob(rtrim($directory, '/').'/*.json') ?: [] as $path) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (! is_array($decoded)) {
                $report?->error('global/'.basename($path), 'not valid JSON');

                continue;
            }

            $files[basename($path)] = $decoded;
        }

        if ($files === []) {
            $report?->error('global', "no global screen files in {$directory}");
        }

        return self::fromFiles($files, $report);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function outcome(string $id): ?array
    {
        return $this->outcomes[$id] ?? null;
    }
}
