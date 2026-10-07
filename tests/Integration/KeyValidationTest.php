<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Reference-pool key validation; PSR-6 does not require everything asserted here.
 */
final class KeyValidationTest extends ReferencePoolTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedKeys(): array
    {
        return Psr6PoolTestCase::reservedKeys();
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonStringKeys(): array
    {
        return Psr6PoolTestCase::nonStringKeys();
    }

    /**
     * Reference-pool behavior; PSR-6 does not require this.
     *
     * @dataProvider reservedKeys
     */
    public function testBulkOperationsRejectReservedKeysWithoutPartialDeletes(string $key): void
    {
        $this->saveValue('key1', 'one');
        $this->saveValue('key2', 'two');

        try {
            $this->pool()->getItems(['key1', $key, 'key2']);
            $this->fail('getItems accepted an invalid key');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cache key "' . $key . '"', $exception->getMessage());
        }

        try {
            $this->pool()->deleteItems(['key1', $key, 'key2']);
            $this->fail('deleteItems accepted an invalid key');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cache key "' . $key . '"', $exception->getMessage());
        }

        $this->assertTrue($this->pool()->hasItem('key1'));
        $this->assertTrue($this->pool()->hasItem('key2'));
        $this->assertSame('one', $this->pool()->getItem('key1')->get());
        $this->assertSame('two', $this->pool()->getItem('key2')->get());
    }

    /**
     * Reference-pool behavior; PSR-6 does not require this.
     *
     * @dataProvider nonStringKeys
     */
    public function testDeleteItemsRejectsNonStringKeysWithoutPartialDeletes(mixed $key): void
    {
        $this->saveValue('key1', 'one');
        $this->saveValue('key2', 'two');

        try {
            $this->pool()->deleteItems(['key1', $key, 'key2']);
            $this->fail('deleteItems accepted a non-string key');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('string', $exception->getMessage());
        }

        $this->assertSame('one', $this->pool()->getItem('key1')->get());
        $this->assertSame('two', $this->pool()->getItem('key2')->get());
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testSaveRejectsAnItemWhoseKeyIsReserved(): void
    {
        $item = new class implements CacheItemInterface {
            public function getKey(): string
            {
                return 'bad:key';
            }

            public function get(): mixed
            {
                return 'value';
            }

            public function isHit(): bool
            {
                return true;
            }

            public function set(mixed $value): static
            {
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

        $this->expectException(InvalidArgumentException::class);
        $this->pool()->save($item);
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
    public function testSaveDeferredRejectsAnItemWhoseKeyIsReserved(): void
    {
        $this->saveValue('ok', 'kept');

        $bad = new class implements CacheItemInterface {
            public function getKey(): string
            {
                return 'a/b';
            }

            public function get(): mixed
            {
                return 'value';
            }

            public function isHit(): bool
            {
                return true;
            }

            public function set(mixed $value): static
            {
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

        try {
            $this->pool()->saveDeferred($bad);
            $this->fail('saveDeferred accepted a reserved key');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cache key "a/b"', $exception->getMessage());
        }

        $this->assertTrue($this->pool()->hasItem('ok'));
        $this->assertSame('kept', $this->pool()->getItem('ok')->get());
    }
}
