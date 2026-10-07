<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\Tests\Fixture\ArrayCachePool;
use Psr\Cache\Tests\Fixture\ArrayCacheStorage;
use Psr\Cache\Tests\Fixture\FrozenClock;

/**
 * Runs {@see Psr6PoolTestCase} against the in-memory reference pool in tests/Fixture.
 */
final class ReferencePoolPsr6Test extends Psr6PoolTestCase
{
    private ArrayCacheStorage $storage;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->storage = new ArrayCacheStorage();
        $this->clock = new FrozenClock(1700000000);
        parent::setUp();
    }

    protected function createCachePool(): CacheItemPoolInterface
    {
        return new ArrayCachePool($this->storage, $this->clock);
    }
}
