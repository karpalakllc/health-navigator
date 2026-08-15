<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reset throttling between requests within a single test.
     *
     * RateLimiter::clear() takes a *bucket key*, not a limiter name, so the
     * long-standing `RateLimiter::clear('api-login')` idiom in this suite did
     * nothing — those tests passed because they stayed under the limit, not
     * because anything was cleared. Flushing the array cache store is what
     * actually resets the counters.
     */
    protected function forgetRateLimits(): void
    {
        Cache::flush();
    }
}
