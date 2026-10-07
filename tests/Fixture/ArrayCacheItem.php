<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Reference cache item used only by {@see ArrayCachePool}.
 *
 * This class is test code. It is not part of the psr/cache package, and it
 * does not cover src/. The reflection tests under tests/Contract lock the
 * published interfaces.
 *
 * isHit() and get() describe the result of the lookup that produced this
 * object. They do not change while the item is held; expiry is applied on
 * the next pool lookup or save. set() marks the item as carrying a value for
 * a subsequent save. A miss has isHit() false and get() null. A stored null
 * is a hit with get() null until the pool reports it expired on a later lookup.
 */
final class ArrayCacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private mixed $value,
        private bool $hit,
        private ?float $expiry,
        private Clock $clock
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        if (!$this->hit) {
            return null;
        }

        return $this->value;
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
        if ($expiration === null) {
            $this->expiry = null;

            return $this;
        }

        $this->expiry = (float) $expiration->format('U.u');

        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiry = null;

            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expiry = (float) self::fromClock($this->clock)->add($time)->format('U.u');

            return $this;
        }

        $this->expiry = $this->clock->now() + $time;

        return $this;
    }

    public function expiry(): ?float
    {
        return $this->expiry;
    }

    /**
     * Value passed to set(), including when the item is not a hit.
     */
    public function rawValue(): mixed
    {
        return $this->value;
    }

    private static function fromClock(Clock $clock): DateTimeImmutable
    {
        $now = $clock->now();
        $seconds = (int) floor($now);
        $microseconds = (int) round(($now - $seconds) * 1000000);
        if ($microseconds === 1000000) {
            ++$seconds;
            $microseconds = 0;
        }

        $moment = DateTimeImmutable::createFromFormat('U u', sprintf('%d %06d', $seconds, $microseconds));
        if (!$moment instanceof DateTimeImmutable) {
            return new DateTimeImmutable('@' . $seconds);
        }

        return $moment;
    }
}
