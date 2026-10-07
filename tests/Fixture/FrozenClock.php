<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

/**
 * Deterministic clock so expiration tests do not sleep.
 */
final class FrozenClock implements Clock
{
    public function __construct(private float $now)
    {
    }

    public function now(): float
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
