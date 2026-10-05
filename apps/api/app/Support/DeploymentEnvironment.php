<?php

namespace App\Support;

/**
 * The one list of environments that are not a deployment: a developer's
 * machine, a shared development box and the test suite.
 *
 * Every relaxation keyed on APP_ENV reads it from here — demo seeding, the
 * localhost CORS origins, the documented default admin address, preflight
 * enforcement — so that one environment is never treated as deployed by one
 * check and as a sandbox by another. (They disagreed about `development`: the
 * seeder created admin@zdravje360.test there while platform:bootstrap refused
 * that very address, and preflight enforced rules CORS itself relaxed.)
 *
 * A plain constant so config files, which load before the environment is
 * detected, can compare env('APP_ENV') against the same list.
 */
final class DeploymentEnvironment
{
    public const NON_DEPLOYED = ['local', 'development', 'testing'];

    public static function isDeployed(): bool
    {
        return ! app()->environment(self::NON_DEPLOYED);
    }
}
