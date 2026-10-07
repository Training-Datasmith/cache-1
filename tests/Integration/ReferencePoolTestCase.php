<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\Tests\Fixture\ArrayCachePool;
use Psr\Cache\Tests\Fixture\ArrayCacheStorage;
use Psr\Cache\Tests\Fixture\FrozenClock;

/**
 * Harness for the in-memory reference pool in tests/Fixture.
 *
 * The fixture is a reference implementation used to exercise PSR-6 behavior.
 * It is not production code and these tests are not coverage of src/.
 * A real pool can extend CachePoolIntegrationTestCase and implement
 * createCachePool() on its own.
 */
abstract class ReferencePoolTestCase extends CachePoolIntegrationTestCase
{
    protected ArrayCacheStorage $storage;

    protected FrozenClock $clock;

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
