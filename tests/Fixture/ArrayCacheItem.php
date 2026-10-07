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
 * isHit() and get() share one decision: a value was assigned by set() or by
 * a cache hit, and its expiry is still in the future. After that instant both
 * report a miss. An untouched miss has no assigned value, so isHit() is false
 * and get() returns null. A stored null is an assigned value and stays a hit
 * until it expires.
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
        if (!$this->isHit()) {
            return null;
        }

        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit && !$this->isExpired();
    }

    /**
     * True when set() or a cache hit assigned a value, including after expiry.
     *
     * The reference pool uses this to tell an untouched miss from a value that
     * has since expired. The expired value is deleted on save; the miss is not
     * turned into a stored null.
     */
    public function hasValue(): bool
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

    private function isExpired(): bool
    {
        return $this->expiry !== null && $this->clock->now() >= $this->expiry;
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
