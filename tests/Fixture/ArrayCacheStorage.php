<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

/**
 * Shared backend for reference pools. Instances that wrap the same storage
 * observe each other's committed writes, including writes flushed from __destruct().
 *
 * @phpstan-type Record array{value: mixed, expiry: float|null}
 */
final class ArrayCacheStorage
{
    /**
     * @var array<string, array{value: mixed, expiry: float|null}>
     */
    private array $items = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * @return array{value: mixed, expiry: float|null}|null
     */
    public function get(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?float $expiry): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expiry' => $expiry,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
