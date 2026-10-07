<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Behavioral checks for a PSR-6 cache pool.
 *
 * This case does not cover src/. The package only publishes interfaces, and
 * the reflection tests in tests/Contract lock that API. Subclasses supply a
 * pool through createCachePool(), so the same checks can target a real
 * implementation. ReferencePoolTestCase wires the in-memory reference pool
 * under tests/Fixture, which is test code rather than package code.
 */
abstract class CachePoolIntegrationTestCase extends TestCase
{
    protected ?CacheItemPoolInterface $pool = null;

    protected function setUp(): void
    {
        $this->pool = $this->createCachePool();
    }

    protected function tearDown(): void
    {
        if ($this->pool !== null) {
            $this->pool->clear();
            $this->pool = null;
        }
    }

    /**
     * Return a pool for one test. A second call may share the same backend
     * so committed writes are visible across instances.
     */
    abstract protected function createCachePool(): CacheItemPoolInterface;

    protected function saveValue(string $key, mixed $value): CacheItemInterface
    {
        $item = $this->pool()->getItem($key);
        $item->set($value);
        $this->assertTrue($this->pool()->save($item));

        return $item;
    }

    protected function pool(): CacheItemPoolInterface
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);

        return $this->pool;
    }
}
