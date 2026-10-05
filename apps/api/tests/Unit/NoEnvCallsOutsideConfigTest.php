<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * After `php artisan config:cache` the .env file is no longer loaded, so every
 * env() call outside config/ silently returns its default. In a seeder that
 * meant demo passwords of "password" no matter what the environment said, and
 * SEED_LOCAL_DEMO being ignored. Read through config() instead.
 */
class NoEnvCallsOutsideConfigTest extends TestCase
{
    private const DIRECTORIES = ['app', 'bootstrap', 'database', 'routes', 'resources'];

    public function test_env_is_only_called_from_config_files(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (self::DIRECTORIES as $directory) {
            $path = $root.'/'.$directory;

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/bootstrap/cache/')) {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    // A bare env( call; not ->env(, ::env( or a longer identifier.
                    if (preg_match('/(?<![\w>:$\\\\])env\s*\(/', $line) === 1) {
                        $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.($number + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'env() outside config/ returns its default once config is cached. Move these behind a config key: '.implode(', ', $offenders));
    }
}
