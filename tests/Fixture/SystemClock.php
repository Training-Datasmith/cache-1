<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

/**
 * Wall clock used when a test does not inject its own time source.
 */
final class SystemClock implements Clock
{
    public function now(): float
    {
        return microtime(true);
    }
}
