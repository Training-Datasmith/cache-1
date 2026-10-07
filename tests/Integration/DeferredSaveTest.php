<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

/**
 * Reference-pool deferred-save behavior; PSR-6 does not require everything here.
 */
final class DeferredSaveTest extends ReferencePoolTestCase
{
    /**
     * Reference-pool behavior; PSR-6 does not require immediate visibility after
     * destruction, only eventual persistence of deferred items.
     */
    public function testDeferredItemsAreCommittedWhenThePoolIsDestroyed(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('4711');
        $this->assertTrue($this->pool()->saveDeferred($item));
        unset($item);

        $pool = $this->pool;
        $this->pool = null;
        unset($pool);
        gc_collect_cycles();

        $reloaded = $this->createCachePool();
        $this->pool = $reloaded;
        $loaded = $reloaded->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('4711', $loaded->get());
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testImmediateSaveWinsOverADeferredValue(): void
    {
        $deferred = $this->pool()->getItem('key');
        $deferred->set('deferred');
        $this->pool()->saveDeferred($deferred);

        $immediate = $this->pool()->getItem('key');
        $immediate->set('immediate');
        $this->assertTrue($this->pool()->save($immediate));
        $this->assertTrue($this->pool()->commit());

        $this->assertSame('immediate', $this->pool()->getItem('key')->get());
        $this->assertSame('immediate', $this->createCachePool()->getItem('key')->get());
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testExpiryChangeAfterQueueingDoesNotAffectTheQueuedRecord(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(10);
        $this->pool()->saveDeferred($item);
        $item->expiresAfter(1);

        $this->clock->advance(2);
        $this->assertTrue($this->pool()->hasItem('key'));
        $this->assertSame('value', $this->pool()->getItem('key')->get());

        $this->clock->advance(8);
        $this->assertFalse($this->pool()->hasItem('key'));
    }
}
