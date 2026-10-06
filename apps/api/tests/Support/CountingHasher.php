<?php

namespace Tests\Support;

use Illuminate\Contracts\Hashing\Hasher;

/**
 * Delegates to the real hasher and counts the expensive calls, so a test can
 * assert that two code paths pay for the same amount of bcrypt work.
 */
class CountingHasher implements Hasher
{
    public int $calls = 0;

    public function __construct(private readonly Hasher $inner) {}

    /**
     * @return array<string, mixed>
     */
    public function info($hashedValue): array
    {
        return $this->inner->info($hashedValue);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function make($value, array $options = []): string
    {
        $this->calls++;

        return $this->inner->make($value, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function check($value, $hashedValue, array $options = []): bool
    {
        $this->calls++;

        return $this->inner->check($value, $hashedValue, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        return $this->inner->needsRehash($hashedValue, $options);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }
}
