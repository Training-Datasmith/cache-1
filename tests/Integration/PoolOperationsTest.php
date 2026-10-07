<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Reference-pool behavior; PSR-6 does not require everything asserted here.
 */
final class PoolOperationsTest extends ReferencePoolTestCase
{
    /** Reference-pool behavior; PSR-6 does not require this. */
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

    /** Reference-pool behavior; PSR-6 does not require this. */
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

    public function testHasItemTracksPresenceAndExpiry(): void
    {
        $this->assertFalse($this->pool()->hasItem('missing'));

        $item = $this->pool()->getItem('key');
        $item->set('value')->expiresAfter(2);
        $this->assertTrue($this->pool()->save($item));
        $this->assertTrue($this->pool()->hasItem('key'));

        $this->clock->advance(2);
        $this->assertFalse($this->pool()->hasItem('key'));
    }

    /** Reference-pool behavior; PSR-6 does not require this. */
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
}
