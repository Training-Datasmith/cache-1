<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use stdClass;

/**
 * Clock-free behavioral checks required by PSR-6.
 *
 * Does not cover src/. Subclasses implement createCachePool() to run these
 * against any CacheItemPoolInterface implementation.
 */
abstract class Psr6PoolTestCase extends CachePoolIntegrationTestCase
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

    public function testSetNullIsAHitDistinctFromAMiss(): void
    {
        $item = $this->pool()->getItem('key');
        $this->assertSame($item, $item->set(null));
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

        sort($seen);
        $expected = $requested;
        sort($expected);
        $this->assertSame($expected, $seen);
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

    public function testGetItemsPreservesNumericStringKeys(): void
    {
        $this->saveValue('123', 'numeric');

        $count = 0;
        foreach ($this->pool()->getItems(['123']) as $key => $item) {
            $this->assertSame('123', $key);
            $this->assertSame('123', $item->getKey());
            $this->assertTrue($item->isHit());
            $this->assertSame('numeric', $item->get());
            ++$count;
        }

        $this->assertSame(1, $count);
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

    public function testCommittedWritesAreVisibleFromAnotherPoolInstance(): void
    {
        $this->saveValue('key', 'shared');

        $other = $this->createCachePool();
        $this->assertTrue($other->hasItem('key'));
        $this->assertSame('shared', $other->getItem('key')->get());
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

    public function testExpiresAtInTheFutureAndThePast(): void
    {
        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAt(new DateTimeImmutable('@4102444800'));
        $this->pool()->save($item);

        $this->assertTrue($this->pool()->getItem('key')->isHit());

        $item = $this->pool()->getItem('key');
        $item->set('value');
        $item->expiresAt(new DateTimeImmutable('@1'));
        $this->pool()->save($item);

        $loaded = $this->pool()->getItem('key');
        $this->assertFalse($loaded->isHit());
        $this->assertNull($loaded->get());
        $this->assertFalse($this->pool()->hasItem('key'));
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
            $this->pool()->save($item);
            $this->assertFalse($this->pool()->hasItem('key'), 'TTL ' . $ttl);
            $this->assertNull($this->pool()->getItem('key')->get());
        }
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

        $this->assertTrue($this->pool()->commit());
        $this->assertSame('4711', $this->pool()->getItem('key')->get());
        $this->assertSame('4712', $this->createCachePool()->getItem('key2')->get());

        $this->assertTrue($this->pool()->commit());
        $this->assertSame('4711', $this->pool()->getItem('key')->get());
    }

    public function testDeferredSaveOfAnAlreadyExpiredItemDoesNotStoreIt(): void
    {
        $this->saveValue('key', 'previous');

        $item = $this->pool()->getItem('key');
        $item->set('4711');
        $item->expiresAt(new DateTimeImmutable('@1'));
        $this->pool()->saveDeferred($item);

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
        $this->assertFalse($this->createCachePool()->hasItem('key'));
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
        $this->assertSame('value', $this->createCachePool()->getItem('key')->get());
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

        ksort($values);
        $this->assertSame([
            'deferred' => [true, 'from-queue'],
            'missing' => [false, null],
            'stored' => [true, 'from-storage'],
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedKeys(): array
    {
        return [
            'empty' => [''],
            'open brace' => ['{'],
            'close brace' => ['}'],
            'open paren' => ['('],
            'close paren' => [')'],
            'slash' => ['a/b'],
            'backslash' => ["a\\b"],
            'at' => ['user@host'],
            'colon' => ['namespace:key'],
            'embedded brace' => ['rand{str'],
            'embedded close brace' => ['rand}str'],
            'only backslash' => ['\\'],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonStringKeys(): array
    {
        return [
            'integer' => [2],
            'float' => [2.5],
            'true' => [true],
            'false' => [false],
            'null' => [null],
            'array' => [['array']],
            'object' => [new stdClass()],
        ];
    }

    /**
     * @dataProvider reservedKeys
     */
    public function testSingleKeyOperationsRejectReservedKeys(string $key): void
    {
        foreach (['getItem', 'hasItem', 'deleteItem'] as $method) {
            try {
                $this->pool()->{$method}($key);
                $this->fail($method . ' accepted ' . var_export($key, true));
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * @dataProvider reservedKeys
     */
    public function testBulkOperationsRejectReservedKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool()->getItems(['key1', $key, 'key2']);
    }

    /**
     * @dataProvider reservedKeys
     */
    public function testDeleteItemsRejectReservedKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool()->deleteItems(['key1', $key, 'key2']);
    }

    /**
     * @dataProvider nonStringKeys
     */
    public function testBulkOperationsRejectNonStringKeys(mixed $key): void
    {
        $this->assertInvalidArgumentOrTypeError(function () use ($key): void {
            $this->pool()->getItems(['key1', $key, 'key2']);
        });
    }

    /**
     * @dataProvider nonStringKeys
     */
    public function testDeleteItemsRejectNonStringKeys(mixed $key): void
    {
        $this->assertInvalidArgumentOrTypeError(function () use ($key): void {
            $this->pool()->deleteItems(['key1', $key, 'key2']);
        });
    }

    private function assertInvalidArgumentOrTypeError(callable $callable): void
    {
        try {
            $callable();
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        } catch (\TypeError $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $this->fail('Expected InvalidArgumentException or TypeError.');
    }
}
