<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\Tests\Fixture\ArrayCachePool;
use Psr\Cache\Tests\Fixture\ArrayCacheStorage;
use Psr\Cache\Tests\Fixture\FrozenClock;

abstract class ArrayCachePoolTestCase extends TestCase
{
    protected ArrayCacheStorage $storage;

    protected FrozenClock $clock;

    protected ?ArrayCachePool $pool = null;

    protected function setUp(): void
    {
        $this->storage = new ArrayCacheStorage();
        $this->clock = new FrozenClock(1700000000);
        $this->pool = new ArrayCachePool($this->storage, $this->clock);
    }

    protected function tearDown(): void
    {
        if ($this->pool !== null) {
            $this->pool->clear();
            $this->pool = null;
        }
    }

    protected function createPool(): ArrayCachePool
    {
        return new ArrayCachePool($this->storage, $this->clock);
    }

    protected function saveValue(string $key, mixed $value): CacheItemInterface
    {
        $item = $this->pool()->getItem($key);
        $item->set($value);
        $this->assertTrue($this->pool()->save($item));

        return $item;
    }

    protected function pool(): ArrayCachePool
    {
        $this->assertInstanceOf(ArrayCachePool::class, $this->pool);

        return $this->pool;
    }
}
