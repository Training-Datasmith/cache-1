<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use stdClass;

final class KeyValidationTest extends ArrayCachePoolTestCase
{
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
    public function testBulkOperationsRejectReservedKeysWithoutPartialDeletes(string $key): void
    {
        $this->saveValue('key1', 'one');
        $this->saveValue('key2', 'two');

        try {
            $this->pool()->getItems(['key1', $key, 'key2']);
            $this->fail('getItems accepted an invalid key');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        try {
            $this->pool()->deleteItems(['key1', $key, 'key2']);
            $this->fail('deleteItems accepted an invalid key');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($key === '' ? 'empty' : $key, $exception->getMessage());
        }

        $this->assertTrue($this->pool()->hasItem('key1'));
        $this->assertTrue($this->pool()->hasItem('key2'));
        $this->assertSame('one', $this->pool()->getItem('key1')->get());
        $this->assertSame('two', $this->pool()->getItem('key2')->get());
    }

    /**
     * @dataProvider nonStringKeys
     */
    public function testBulkOperationsRejectNonStringKeys(mixed $key): void
    {
        $this->saveValue('key1', 'one');

        $this->expectException(InvalidArgumentException::class);
        $this->pool()->getItems(['key1', $key, 'key2']);
    }

    /**
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

    public function testTypedStringParametersRejectNonStrings(): void
    {
        foreach (['getItem', 'hasItem', 'deleteItem'] as $method) {
            try {
                $this->pool()->{$method}(123);
                $this->fail($method . ' coerced a non-string key');
            } catch (\TypeError $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

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

    public function testSaveDeferredRejectsAnItemWhoseKeyIsReserved(): void
    {
        $item = $this->pool()->getItem('ok');
        $item->set('value');

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
            $this->assertStringContainsString('a/b', $exception->getMessage());
        }

        $this->assertFalse($this->pool()->hasItem('ok'));
    }
}
