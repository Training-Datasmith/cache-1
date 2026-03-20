<?php

declare(strict_types=1);

/**
 * Example: Minimal in-memory PSR-6 pool implementation.
 *
 * This illustrates what an implementing library must provide.
 * It is intentionally simple — a real implementation would use
 * a persistent backend and handle serialization and TTL correctly.
 */

use Psr\Cache\Cache_Item_Interface;
use Psr\Cache\Cache_Item_Pool_Interface;

/**
 * A trivial in-memory cache item.
 */
final class Memory_Cache_Item implements Cache_Item_Interface
{
    private mixed $value = null;
    private bool $is_hit = false;
    private ?\DateTimeInterface $expires_at = null;

    public function __construct(private readonly string $key) {}

    public function get_key(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->is_hit ? $this->value : null;
    }

    public function is_hit(): bool
    {
        return $this->is_hit;
    }

    public function set(mixed $value): static
    {
        $this->value  = $value;
        $this->is_hit = true;
        return $this;
    }

    public function expires_at(?\DateTimeInterface $expiration): static
    {
        $this->expires_at = $expiration;
        return $this;
    }

    public function expires_after(int|\DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expires_at = null;
        } elseif (is_int($time)) {
            $this->expires_at = new \DateTimeImmutable('+' . $time . ' seconds');
        } else {
            $this->expires_at = (new \DateTimeImmutable())->add($time);
        }
        return $this;
    }

    /** @internal Used by the pool to check expiry. */
    public function is_expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < new \DateTimeImmutable();
    }
}

/**
 * A trivial in-memory PSR-6 pool.
 */
final class Memory_Cache_Pool implements Cache_Item_Pool_Interface
{
    /** @var array<string, Memory_Cache_Item> */
    private array $store = [];

    /** @var array<string, Memory_Cache_Item> */
    private array $deferred = [];

    public function get_item(string $key): Cache_Item_Interface
    {
        if (isset($this->store[$key]) && !$this->store[$key]->is_expired()) {
            return $this->store[$key];
        }
        return new Memory_Cache_Item($key);
    }

    public function get_items(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->get_item($key);
        }
        return $items;
    }

    public function has_item(string $key): bool
    {
        return isset($this->store[$key]) && !$this->store[$key]->is_expired();
    }

    public function clear(): bool
    {
        $this->store    = [];
        $this->deferred = [];
        return true;
    }

    public function delete_item(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function delete_items(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }
        return true;
    }

    public function save(Cache_Item_Interface $item): bool
    {
        $this->store[$item->get_key()] = $item;
        return true;
    }

    public function save_deferred(Cache_Item_Interface $item): bool
    {
        $this->deferred[$item->get_key()] = $item;
        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->store[$item->get_key()] = $item;
        }
        $this->deferred = [];
        return true;
    }

    public function __destruct()
    {
        $this->commit();
    }
}

// Usage
$pool = new Memory_Cache_Pool();
$item = $pool->get_item('greeting');

if (!$item->is_hit()) {
    $item->set('Hello, World!')->expires_after(60);
    $pool->save($item);
}

echo $pool->get_item('greeting')->get(); // Hello, World!
