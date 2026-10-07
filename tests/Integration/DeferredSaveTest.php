<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateTimeImmutable;

final class DeferredSaveTest extends ArrayCachePoolTestCase
{
    public function testDeferredItemsAreHitsBeforeAndAfterCommit(): void
    {
        $first = $this->pool()->getItem('key');
        $first->set('4711');
        $this->assertTrue($this->pool()->saveDeferred($first));

        $second = $this->pool()->getItem('key2');
        $second->set('4712');
        $this->assertTrue($this->pool()->saveDeferred($second));

        $this->assertTrue($this->pool()->hasItem('key'));
        $this->assertTrue($this->pool()->getItem('key')->isHit());
        $this->assertSame('4711', $this->pool()->getItem('key')->get());
        $this->assertTrue($this->pool()->getItem('key2')->isHit());
        $this->assertSame('4712', $this->pool()->getItem('key2')->get());

        $other = $this->createPool();
        $this->assertFalse($other->hasItem('key'));
        $this->assertFalse($other->hasItem('key2'));

        $this->assertTrue($this->pool()->commit());
        $this->assertSame('4711', $this->pool()->getItem('key')->get());
        $this->assertSame('4712', $other->getItem('key2')->get());

        $this->assertTrue($this->pool()->commit());
        $this->assertSame('4711', $this->pool()->getItem('key')->get());
    }

    public function testDeferredSaveOfAnAlreadyExpiredItemDoesNotStoreIt(): void
    {
        $this->saveValue('key', 'previous');

        $item = $this->pool()->getItem('key');
        $item->set('4711');
        $item->expiresAt(new DateTimeImmutable('@1699999999'));
        $this->assertTrue($this->pool()->saveDeferred($item));

        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertTrue($this->pool()->commit());
        $loaded = $this->pool()->getItem('key');
        $this->assertFalse($loaded->isHit());
        $this->assertNull($loaded->get());
    }

    public function testDeleteRemovesADeferredItemBeforeCommit(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('4711');
        $this->pool()->saveDeferred($item);
        $this->assertTrue($this->pool()->getItem('key')->isHit());

        $this->assertTrue($this->pool()->deleteItem('key'));
        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertFalse($this->pool()->getItem('key')->isHit());

        $this->assertTrue($this->pool()->commit());
        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertFalse($this->createPool()->hasItem('key'));
    }

    public function testClearDropsDeferredItems(): void
    {
        $this->saveValue('kept-until-clear', 'value');
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $this->pool()->saveDeferred($item);

        $this->assertTrue($this->pool()->clear());
        $this->assertTrue($this->pool()->commit());

        $this->assertFalse($this->pool()->getItem('key')->isHit());
        $this->assertFalse($this->pool()->hasItem('kept-until-clear'));
    }

    public function testDeferredItemsAreCommittedWhenThePoolIsDestroyed(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('4711');
        $this->assertTrue($this->pool()->saveDeferred($item));

        $pool = $this->pool;
        $this->pool = null;
        unset($pool);
        gc_collect_cycles();

        $reloaded = $this->createPool();
        $this->pool = $reloaded;
        $loaded = $reloaded->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('4711', $loaded->get());
    }

    public function testChangingAnItemAfterQueueingDoesNotChangeTheQueuedValue(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $this->pool()->saveDeferred($item);

        $view = $this->pool()->getItem('key');
        $view->set('new value');

        $queued = $this->pool()->getItem('key');
        $this->assertSame('value', $queued->get());

        $this->pool()->commit();
        $this->assertSame('value', $this->pool()->getItem('key')->get());
        $this->assertSame('value', $this->createPool()->getItem('key')->get());
    }

    public function testLaterDeferredSaveOverwritesAnEarlierOne(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $this->pool()->saveDeferred($item);

        $item = $this->pool()->getItem('key');
        $item->set('new value');
        $this->pool()->saveDeferred($item);

        $this->assertSame('new value', $this->pool()->getItem('key')->get());
        $this->pool()->commit();
        $this->assertSame('new value', $this->pool()->getItem('key')->get());
    }

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
        $this->assertSame('immediate', $this->createPool()->getItem('key')->get());
    }

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

    public function testGetItemsSeesDeferredValues(): void
    {
        $this->saveValue('stored', 'from-storage');

        $deferred = $this->pool()->getItem('deferred');
        $deferred->set('from-queue');
        $this->pool()->saveDeferred($deferred);

        $values = [];
        foreach ($this->pool()->getItems(['stored', 'deferred', 'missing']) as $key => $item) {
            $values[$key] = [$item->isHit(), $item->get()];
        }

        $this->assertSame([
            'stored' => [true, 'from-storage'],
            'deferred' => [true, 'from-queue'],
            'missing' => [false, null],
        ], $values);
    }

    public function testDeleteItemsDropsDeferredKeysBeforeCommit(): void
    {
        foreach (['foo', 'bar'] as $key) {
            $item = $this->pool()->getItem($key);
            $item->set($key);
            $this->pool()->saveDeferred($item);
        }

        $this->assertTrue($this->pool()->deleteItems(['foo']));
        $this->assertTrue($this->pool()->commit());

        $this->assertFalse($this->pool()->hasItem('foo'));
        $this->assertSame('bar', $this->pool()->getItem('bar')->get());
    }
}
