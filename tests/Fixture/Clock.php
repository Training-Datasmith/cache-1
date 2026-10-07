<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

/**
 * Source of "now" for expiration math in the in-memory pool.
 */
interface Clock
{
    public function now(): float;
}
