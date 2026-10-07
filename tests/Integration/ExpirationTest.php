<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateInterval;
use DateTime;
use DateTimeImmutable;

/**
 * Reference-pool expiration behavior; PSR-6 does not require everything here.
 */
final class ExpirationTest extends ReferencePoolTestCase
{
    public function testItemRemainsUntilTheTtlElapses(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $this->assertSame($item, $item->expiresAfter(2));
        $this->assertTrue($this->pool()->save($item));

        $this->clock->advance(1);
        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('value', $loaded->get());
        $this->assertTrue($this->pool()->hasItem('key'));

        $this->clock->advance(1);
        $expired = $this->pool()->getItem('key');
        $this->assertFalse($expired->isHit());
        $this->assertNull($expired->get());
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    public function testHeldItemKeepsLookupResultAfterExpiry(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(2);
        $this->pool()->save($item);

        $held = $this->pool()->getItem('key');
        $this->assertTrue($held->isHit());
        $this->assertSame('value', $held->get());

        $this->clock->advance(5);

        $this->assertTrue($held->isHit());
        $this->assertSame('value', $held->get());
        $this->assertFalse($this->pool()->getItem('key')->isHit());
        $this->assertNull($this->pool()->getItem('key')->get());
        $this->assertFalse($this->pool()->hasItem('key'));

        $this->pool()->save($held);
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testExpiresAtSnapshotsAMutableDateTime(): void
    {
        $when = new DateTime('@1700000010');
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAt($when);
        $when->modify('+1 day');
        $this->pool()->save($item);

        $this->clock->advance(11);
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testNullExpirationStoresTheItemPermanently(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(1)->expiresAt(null);
        $this->pool()->save($item);
        $this->clock->advance(100);
        $this->assertTrue($this->pool()->getItem('key')->isHit());
        $this->assertSame('value', $this->pool()->getItem('key')->get());

        $replaced = $this->pool()->getItem('key');
        $replaced->set('later')->expiresAt(new DateTimeImmutable('@1700000005'))->expiresAfter(null);
        $this->pool()->save($replaced);
        $this->clock->advance(100);
        $this->assertSame('later', $this->pool()->getItem('key')->get());
    }

    public function testDateIntervalExpiration(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAfter(new DateInterval('PT1H'));
        $this->pool()->save($item);

        $this->clock->advance(3599);
        $this->assertTrue($this->pool()->hasItem('key'));

        $this->clock->advance(1);
        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertNull($this->pool()->getItem('key')->get());
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testResavingPreservesTheOriginalExpiry(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(10);
        $this->pool()->save($item);

        $loaded = $this->pool()->getItem('key');
        $loaded->set('updated');
        $this->pool()->save($loaded);

        $this->clock->advance(9);
        $this->assertSame('updated', $this->pool()->getItem('key')->get());

        $this->clock->advance(1);
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    public function testExpiredDeferredItemIsNotVisibleAndRemovesAPreviousValue(): void
    {
        $this->saveValue('key', 'old');

        $item = $this->pool()->getItem('key');
        $item->set('new')->expiresAfter(5);
        $this->assertTrue($this->pool()->saveDeferred($item));

        $this->clock->advance(5);
        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertTrue($this->pool()->commit());
        $this->assertFalse($this->pool()->getItem('key')->isHit());
        $this->assertFalse($this->createCachePool()->hasItem('key'));
    }
}
