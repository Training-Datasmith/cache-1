<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\Tests\Fixture\ArrayCachePool;
use Psr\Cache\Tests\Fixture\ArrayCacheStorage;
use stdClass;

final class PoolOperationsTest extends ReferencePoolTestCase
{
    public function testBasicReadWriteDeleteAndClear(): void
    {
        $first = $this->pool()->getItem('key');
        $this->assertFalse($first->isHit());
        $this->assertNull($first->get());
        $this->assertSame($first, $first->set('4711'));
        $this->assertTrue($this->pool()->save($first));

        $second = $this->pool()->getItem('key2');
        $second->set('4712');
        $this->pool()->save($second);

        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('4711', $loaded->get());
        $this->assertSame('key', $loaded->getKey());
        $this->assertTrue($this->pool()->hasItem('key'));

        $loadedTwo = $this->pool()->getItem('key2');
        $this->assertTrue($loadedTwo->isHit());
        $this->assertSame('4712', $loadedTwo->get());

        $this->assertTrue($this->pool()->deleteItem('key'));
        $this->assertFalse($this->pool()->getItem('key')->isHit());
        $this->assertNull($this->pool()->getItem('key')->get());
        $this->assertFalse($this->pool()->hasItem('key'));
        $this->assertTrue($this->pool()->getItem('key2')->isHit());

        $this->assertTrue($this->pool()->deleteItem('missing'));
        $this->assertTrue($this->pool()->clear());
        $this->assertFalse($this->pool()->getItem('key2')->isHit());
        $this->assertFalse($this->pool()->hasItem('key2'));
        $this->assertTrue($this->pool()->clear());
    }

    public function testMissDoesNotBecomeAStoredNullWhenSavedUntouched(): void
    {
        $item = $this->pool()->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertTrue($this->pool()->save($item));
        $this->assertFalse($this->pool()->hasItem('key'));

        $again = $this->pool()->getItem('key');
        $this->assertFalse($again->isHit());
        $this->assertNull($again->get());
    }

    public function testSetNullIsAHitDistinctFromAMiss(): void
    {
        $item = $this->pool()->getItem('key');
        $this->assertSame($item, $item->set(null));
        $this->assertTrue($item->isHit());
        $this->assertNull($item->get());
        $this->pool()->save($item);

        $this->assertTrue($this->pool()->hasItem('key'));
        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertNull($loaded->get());
    }

    public function testGetItemAlwaysReturnsAnItemAndPreservesTheKey(): void
    {
        $missing = $this->pool()->getItem('missing');
        $this->assertInstanceOf(CacheItemInterface::class, $missing);
        $this->assertSame('missing', $missing->getKey());
        $this->assertFalse($missing->isHit());
        $this->assertNull($missing->get());

        $this->saveValue('present', 'value');
        $present = $this->pool()->getItem('present');
        $this->assertInstanceOf(CacheItemInterface::class, $present);
        $this->assertSame('present', $present->getKey());
        $this->assertNotSame($missing, $present);
    }

    public function testItemModifiersAreChainableAndReturnTheSameInstance(): void
    {
        $item = $this->pool()->getItem('key');

        $this->assertSame($item, $item->set('4711'));
        $this->assertSame($item, $item->expiresAfter(2));
        $this->assertSame($item, $item->expiresAt(new DateTime('@1700000100')));
        $this->assertSame($item, $item->set('4711')->expiresAfter(null)->expiresAt(null));
        $this->assertSame('4711', $item->get());
        $this->assertSame('key', $item->getKey());
    }

    public function testGetItemsReturnsOneItemPerKeyAndPreservesKeys(): void
    {
        foreach (['foo', 'bar', 'baz'] as $key) {
            $this->saveValue($key, $key . '-value');
        }

        $requested = ['foo', 'bar', 'baz', 'biz'];
        $items = $this->pool()->getItems($requested);
        $seen = [];

        foreach ($items as $key => $item) {
            $this->assertIsString($key);
            $this->assertInstanceOf(CacheItemInterface::class, $item);
            $this->assertSame($key, $item->getKey());
            $this->assertSame($key !== 'biz', $item->isHit());
            if ($item->isHit()) {
                $this->assertSame($key . '-value', $item->get());
            } else {
                $this->assertNull($item->get());
            }
            $seen[] = $key;
        }

        $this->assertSame($requested, $seen);
    }

    public function testGetItemsWithNoKeysReturnsAnEmptyTraversable(): void
    {
        $this->saveValue('key', 'value');

        foreach ([$this->pool()->getItems(), $this->pool()->getItems([])] as $items) {
            $this->assertIsIterable($items);
            $count = 0;
            foreach ($items as $item) {
                ++$count;
                $this->assertInstanceOf(CacheItemInterface::class, $item);
            }
            $this->assertSame(0, $count);
        }
    }

    public function testGetItemsRepeatsDuplicateKeysAndKeepsNumericStrings(): void
    {
        $this->saveValue('123', 'numeric');
        $this->saveValue('0123', 'padded');

        $seen = [];
        foreach ($this->pool()->getItems(['123', '123', '0123']) as $key => $item) {
            $this->assertIsString($key);
            $seen[] = [$key, $item->get()];
        }

        $this->assertSame([
            ['123', 'numeric'],
            ['123', 'numeric'],
            ['0123', 'padded'],
        ], $seen);
    }

    public function testDeleteItemsRemovesOnlyTheRequestedKeys(): void
    {
        $this->saveValue('foo', 'f');
        $this->saveValue('bar', 'b');
        $this->saveValue('baz', 'z');

        $this->assertTrue($this->pool()->deleteItems(['foo', 'bar', 'missing']));

        $this->assertFalse($this->pool()->hasItem('foo'));
        $this->assertFalse($this->pool()->hasItem('bar'));
        $this->assertTrue($this->pool()->hasItem('baz'));
        $this->assertSame('z', $this->pool()->getItem('baz')->get());
        $this->assertTrue($this->pool()->deleteItems([]));
        $this->assertTrue($this->pool()->hasItem('baz'));
    }

    public function testOverwriteReplacesThePreviousValue(): void
    {
        $this->saveValue('key', 'first');
        $this->saveValue('key', 'second');

        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('second', $loaded->get());
    }

    public function testKeysAreCaseSensitive(): void
    {
        $this->saveValue('Key', 'upper');
        $this->saveValue('key', 'lower');

        $this->assertSame('upper', $this->pool()->getItem('Key')->get());
        $this->assertSame('lower', $this->pool()->getItem('key')->get());
    }

    public function testLoadedItemDoesNotRequeryThePool(): void
    {
        $this->saveValue('key', 'value');
        $item = $this->pool()->getItem('key');

        $this->assertTrue($this->pool()->deleteItem('key'));
        $this->assertTrue($item->isHit());
        $this->assertSame('value', $item->get());
        $this->assertFalse($this->pool()->getItem('key')->isHit());
        $this->assertNull($this->pool()->getItem('key')->get());
    }

    public function testSeparatePoolsShareCommittedStorageOnly(): void
    {
        $this->saveValue('key', 'shared');

        $other = $this->createCachePool();
        $this->assertTrue($other->hasItem('key'));
        $this->assertSame('shared', $other->getItem('key')->get());

        // Separate backend. createCachePool() reuses the shared reference storage.
        $isolated = new ArrayCachePool(new ArrayCacheStorage(), $this->clock);
        $this->assertFalse($isolated->hasItem('key'));

        $this->assertTrue($other->clear());
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    public function testHasItemReadsTheStoredRecord(): void
    {
        $this->assertFalse($this->pool()->hasItem('missing'));

        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(2);
        $this->assertTrue($this->pool()->save($item));
        $this->assertTrue($this->pool()->hasItem('key'));

        $this->clock->advance(2);
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    /**
     * Reference-pool check: the default clock is the system clock. A short TTL
     * must not already be expired at the moment of the write.
     */
    public function testDefaultClockHonorsARelativeTtl(): void
    {
        $pool = new ArrayCachePool(new ArrayCacheStorage());
        $item = $pool->getItem('key');
        $item->set('value')->expiresAfter(60);
        $this->assertTrue($pool->save($item));

        $this->assertTrue($pool->hasItem('key'));
        $loaded = $pool->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame('value', $loaded->get());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function cacheableValues(): array
    {
        return [
            'string' => ['hello'],
            'empty string' => [''],
            'binary' => ["\x00\xff"],
            'integer' => [5],
            'zero' => [0],
            'negative integer' => [-7],
            'float' => [1.23456789],
            'zero float' => [0.0],
            'negative float' => [-0.5],
            'infinity' => [\INF],
            'negative infinity' => [-\INF],
            'true' => [true],
            'false' => [false],
            'null' => [null],
            'list' => [[1, 2, 3]],
            'empty array' => [[]],
            'map' => [['a' => 'foo', 2 => 'bar']],
        ];
    }

    /**
     * @dataProvider cacheableValues
     */
    public function testValuesRoundTripWithTheirOriginalType(mixed $value): void
    {
        $this->saveValue('key', $value);

        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertTrue($this->pool()->hasItem('key'));
        $this->assertSame($value, $loaded->get());
    }

    public function testFullBinaryStringRoundTrips(): void
    {
        $data = '';
        for ($i = 0; $i < 256; ++$i) {
            $data .= chr($i);
        }

        $this->saveValue('key', $data);
        $this->assertSame($data, $this->pool()->getItem('key')->get());
    }

    public function testObjectsRoundTripAsObjects(): void
    {
        $object = new stdClass();
        $object->a = 'foo';
        $this->saveValue('key', $object);

        $loaded = $this->pool()->getItem('key');
        $this->assertTrue($loaded->isHit());
        $this->assertEquals($object, $loaded->get());
        $this->assertInstanceOf(stdClass::class, $loaded->get());

        $moment = new DateTime('@1700000000');
        $this->saveValue('when', $moment);
        $stored = $this->pool()->getItem('when')->get();
        $this->assertInstanceOf(DateTimeInterface::class, $stored);
        $this->assertEquals($moment, $stored);
    }

    public function testForeignCacheItemCanBeSavedWithoutAnExpiry(): void
    {
        $foreign = new class implements CacheItemInterface {
            private mixed $value = null;

            private bool $hit = false;

            public function getKey(): string
            {
                return 'foreign_key';
            }

            public function get(): mixed
            {
                return $this->hit ? $this->value : null;
            }

            public function isHit(): bool
            {
                return $this->hit;
            }

            public function set(mixed $value): static
            {
                $this->value = $value;
                $this->hit = true;

                return $this;
            }

            public function expiresAt(?DateTimeInterface $expiration): static
            {
                return $this;
            }

            public function expiresAfter(int|\DateInterval|null $time): static
            {
                return $this;
            }
        };

        $foreign->set(['outside' => true]);
        $this->assertTrue($this->pool()->save($foreign));

        $loaded = $this->pool()->getItem('foreign_key');
        $this->assertTrue($loaded->isHit());
        $this->assertSame(['outside' => true], $loaded->get());

        $this->clock->advance(86400);
        $this->assertTrue($this->pool()->hasItem('foreign_key'));
    }

    public function testMinimumLegalKeysRoundTrip(): void
    {
        $maximum = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.';
        $this->assertSame(64, strlen($maximum));
        $this->assertSame(1, preg_match('/^[A-Za-z0-9_.]+$/', $maximum));

        $keys = [
            $maximum,
            '.',
            '_',
        ];

        foreach ($keys as $key) {
            $this->saveValue($key, 'value:' . $key);
        }

        foreach ($keys as $key) {
            $item = $this->pool()->getItem($key);
            $this->assertTrue($item->isHit(), $key);
            $this->assertSame($key, $item->getKey());
            $this->assertSame('value:' . $key, $item->get());
            $this->assertTrue($this->pool()->hasItem($key));
        }
    }
}
