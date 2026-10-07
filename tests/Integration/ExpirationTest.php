<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateInterval;
use DateTime;
use DateTimeImmutable;

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

    public function testLoadedItemBecomesAMissWhenItsTtlElapses(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(2);
        $this->pool()->save($item);

        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('value', $loaded->get());

        $this->clock->advance(5);

        $this->assertFalse($loaded->isHit());
        $this->assertNull($loaded->get());
        $this->assertNull($loaded->get());
        $this->assertFalse($loaded->isHit());

        $refetched = $this->pool()->getItem('key');
        $this->assertFalse($refetched->isHit());
        $this->assertNull($refetched->get());
        $this->assertNull($refetched->get());
        $this->assertFalse($refetched->isHit());
    }

    public function testExpiresAtInTheFutureAndThePast(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAt(new DateTimeImmutable('@1700000100'));
        $this->pool()->save($item);

        $this->assertTrue($this->pool()->getItem('key')->isHit());

        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAt(DateTime::createFromFormat('U', '1699999999'));
        $this->assertTrue($this->pool()->save($item));

        $loaded = $this->pool()->getItem('key');
        $this->assertFalse($loaded->isHit());
        $this->assertNull($loaded->get());
        $this->assertFalse($this->pool()->hasItem('key'));
    }

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

    public function testSavingWithoutAnExpiryIsVisibleFromAFreshPool(): void
    {
        $item = $this->pool()->getItem('test_ttl_null');
        $item->set('data');
        $this->pool()->save($item);

        $pool = $this->createCachePool();
        $loaded = $pool->getItem('test_ttl_null');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('data', $loaded->get());
    }

    public function testZeroAndNegativeTtlsAreAlreadyExpired(): void
    {
        foreach ([0, -1, -30] as $ttl) {
            $item = $this->pool()->getItem('key');
            $item->set('value');
            $item->expiresAfter($ttl);
            $this->assertTrue($this->pool()->save($item));
            $this->assertFalse($this->pool()->hasItem('key'), 'TTL ' . $ttl);
            $this->assertNull($this->pool()->getItem('key')->get());
        }
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

    public function testZeroAndInvertedDateIntervalsAreExpired(): void
    {
        $item = $this->pool()->getItem('zero');
        $item->set('value');
        $item->expiresAfter(new DateInterval('PT0S'));
        $this->pool()->save($item);
        $this->assertFalse($this->pool()->hasItem('zero'));

        $inverted = new DateInterval('PT30S');
        $inverted->invert = 1;
        $item = $this->pool()->getItem('past');
        $item->set('value');
        $item->expiresAfter($inverted);
        $this->pool()->save($item);
        $this->assertFalse($this->pool()->hasItem('past'));
    }

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
