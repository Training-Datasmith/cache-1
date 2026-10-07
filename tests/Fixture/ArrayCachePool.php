<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * In-memory PSR-6 pool used to exercise the cache interfaces.
 *
 * Deferred items are visible on this instance before commit(), and they are
 * written to the shared storage on commit() or destruction. A second pool
 * wrapping the same storage does not see deferred items until then.
 *
 * Keys must be non-empty strings and must not contain the reserved characters
 * {}()/\@:. Any other character, including characters outside the minimum
 * A-Z a-z 0-9 _ . set, is accepted, as are keys longer than 64 characters.
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    private Clock $clock;

    /**
     * @var array<string, array{value: mixed, expiry: float|null}>
     */
    private array $deferred = [];

    public function __construct(private ArrayCacheStorage $storage, ?Clock $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->validateKey($key);
        $record = $this->findRecord($key);
        if ($record === null || $this->isExpired($record['expiry'])) {
            return new ArrayCacheItem($key, null, false, null, $this->clock);
        }

        return new ArrayCacheItem($key, $record['value'], true, $record['expiry'], $this->clock);
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        return $this->generateItems($keys);
    }

    public function hasItem(string $key): bool
    {
        $this->validateKey($key);

        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->deferred = [];
        $this->storage->clear();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);
        unset($this->deferred[$key]);
        $this->storage->delete($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->validateKey($key);
        unset($this->deferred[$key]);

        // An untouched miss has no value. Persisting it would invent a cached null.
        if (!$item->isHit()) {
            return true;
        }

        $expiry = $this->expiryOf($item);
        if ($this->isExpired($expiry)) {
            $this->storage->delete($key);

            return true;
        }

        $this->storage->set($key, $this->valueOf($item), $expiry);

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->validateKey($key);

        if (!$item->isHit()) {
            unset($this->deferred[$key]);

            return true;
        }

        $expiry = $this->expiryOf($item);
        if ($this->isExpired($expiry)) {
            unset($this->deferred[$key]);
            $this->storage->delete($key);

            return true;
        }

        // Snapshot the value at queue time. Later set() calls on the same
        // object must not change the queued record.
        $this->deferred[$key] = [
            'value' => $this->valueOf($item),
            'expiry' => $expiry,
        ];

        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $key => $record) {
            $key = (string) $key;
            if ($this->isExpired($record['expiry'])) {
                $this->storage->delete($key);
                continue;
            }

            $this->storage->set($key, $record['value'], $record['expiry']);
        }

        $this->deferred = [];

        return true;
    }

    public function __destruct()
    {
        $this->commit();
    }

    /**
     * @param array<int, mixed> $keys
     */
    private function generateItems(array $keys): iterable
    {
        foreach ($keys as $key) {
            $stringKey = (string) $key;
            yield $stringKey => $this->getItem($stringKey);
        }
    }

    /**
     * @return array{value: mixed, expiry: float|null}|null
     */
    private function findRecord(string $key): ?array
    {
        if (array_key_exists($key, $this->deferred)) {
            return $this->deferred[$key];
        }

        return $this->storage->get($key);
    }

    private function valueOf(CacheItemInterface $item): mixed
    {
        if ($item instanceof ArrayCacheItem) {
            return $item->rawValue();
        }

        return $item->get();
    }

    private function expiryOf(CacheItemInterface $item): ?float
    {
        if ($item instanceof ArrayCacheItem) {
            return $item->expiry();
        }

        return null;
    }

    private function isExpired(?float $expiry): bool
    {
        return $expiry !== null && $this->clock->now() >= $expiry;
    }

    private function validateKey(mixed $key): void
    {
        if (!is_string($key) || $key === '' || preg_match('#[{\}()/@\\\\:]#', $key) === 1) {
            throw new InvalidCacheArgumentException($this->invalidKeyMessage($key));
        }
    }

    private function invalidKeyMessage(mixed $key): string
    {
        if (!is_string($key)) {
            return 'Cache key must be a string, ' . get_debug_type($key) . ' given.';
        }

        if ($key === '') {
            return 'Cache key must not be empty.';
        }

        return 'Cache key "' . $key . '" contains reserved characters {}()/\@:.';
    }
}
