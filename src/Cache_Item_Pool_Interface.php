<?php

declare (strict_types=1);
namespace Psr\Cache;

/**
 * Generates CacheItemInterface objects and manages the backing cache store.
 *
 * The primary purpose of Cache\CacheItemPoolInterface is to accept a key from
 * the Calling Library and return the associated Cache\CacheItemInterface object.
 * It is also the primary point of interaction with the entire cache collection.
 * All configuration and initialization of the Pool is left up to an
 * Implementing Library.
 *
 * A pool represents a complete, logically isolated cache namespace. Multiple
 * pools may coexist (e.g., a "users" pool and a "products" pool), each with
 * its own keyspace and potentially its own storage backend.
 *
 * @since 1.0
 * @see https://www.php-fig.org/psr/psr-6/
 */
interface Cache_Item_Pool_Interface
{
    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null. On a miss, the returned item's
     * is_hit() will return false and get() will return null.
     *
     * @param string $key The unique cache key identifying the item to retrieve.
     *   Keys MUST consist of characters from the set A-Z, a-z, 0-9, _, and .
     *   with a minimum length of 1 and a maximum length of 64 characters.
     *
     * @throws \Psr\Cache\InvalidArgumentException If the $key string is not a
     *   legal value according to the pool's key restrictions.
     *
     * @return Cache_Item_Interface The corresponding Cache Item. On a cache miss
     *   the item's is_hit() returns false; it may then be populated via set()
     *   and saved back to the pool.
     *
     * @since 1.0
     */
    public function get_item(string $key): Cache_Item_Interface;
    /**
     * Returns a traversable set of cache items.
     *
     * Fetching multiple items in a single call allows implementations to
     * batch backend requests (e.g., a single Redis MGET) rather than issuing
     * one round-trip per key, which can be significantly more efficient.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve.
     *   An empty array is legal and will return an empty iterable immediately.
     *
     * @throws \Psr\Cache\InvalidArgumentException If any of the keys in $keys
     *   are not legal values according to the pool's key restrictions.
     *
     * @return iterable<string, Cache_Item_Interface> An iterable collection of
     *   Cache Items keyed by the cache keys of each item. A Cache item will be
     *   returned for each key, even if that key is not found. If no keys are
     *   specified then an empty traversable MUST be returned instead.
     *
     * @complexity O(n) where n is the number of keys; implementations SHOULD
     *   issue a single batched backend request.
     * @since 1.0
     */
    public function get_items(array $keys = []): iterable;
    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance
     * reasons, which means it avoids the overhead of deserializing the stored
     * data. However, this creates a TOCTOU (time-of-check-to-time-of-use) race
     * condition: between has_item() returning true and get_item() being called,
     * another process may evict the item. To avoid this, prefer calling
     * get_item() and checking is_hit() on the returned item.
     *
     * @param string $key The cache key whose existence should be verified.
     *
     * @throws \Psr\Cache\InvalidArgumentException If the $key string is not a
     *   legal value according to the pool's key restrictions.
     *
     * @return bool True if a valid, non-expired entry exists for this key.
     *   False if the key is absent or its entry has expired.
     *
     * @see Cache_Item_Interface::is_hit() Preferred alternative that avoids TOCTOU.
     * @since 1.0
     */
    public function has_item(string $key): bool;
    /**
     * Deletes all items in the pool.
     *
     * This is a bulk operation affecting every cached key in this pool's
     * namespace. It does NOT clear other pools, even if they share the same
     * backend. Use with caution in production; this is primarily intended for
     * cache-warming scenarios or test setup/teardown.
     *
     * @return bool True if the pool was successfully cleared. False if there
     *   was an error preventing the full clear (partial clears are possible).
     *
     * @since 1.0
     */
    public function clear(): bool;
    /**
     * Removes the item from the pool.
     *
     * Deleting a key that does not exist MUST return true; the post-condition
     * of the operation (the key is absent) is satisfied either way.
     *
     * @param string $key The unique cache key of the item to remove.
     *
     * @throws \Psr\Cache\InvalidArgumentException If the $key string is not a
     *   legal value according to the pool's key restrictions.
     *
     * @return bool True if the item was successfully removed or did not exist.
     *   False only if an error occurred during the delete operation itself.
     *
     * @since 1.0
     */
    public function delete_item(string $key): bool;
    /**
     * Removes multiple items from the pool.
     *
     * Like delete_item(), deleting keys that do not exist is not an error.
     * Implementations SHOULD batch backend deletes for efficiency. Returns
     * false only if at least one deletion failed due to a backend error.
     *
     * @param string[] $keys An array of keys that should be removed from the pool.
     *
     * @throws \Psr\Cache\InvalidArgumentException If any of the keys in $keys
     *   are not legal values according to the pool's key restrictions.
     *
     * @return bool True if all specified items were successfully removed (or
     *   were already absent). False if any deletion encountered a backend error.
     *
     * @complexity O(n) where n is the number of keys; implementations SHOULD
     *   issue a single batched backend request.
     * @since 1.0
     */
    public function delete_items(array $keys): bool;
    /**
     * Persists a cache item immediately.
     *
     * The item MUST have been obtained from this pool via get_item() or
     * get_items(). Passing an item from a different pool's implementation
     * results in undefined behavior. The item's key, value, and expiry are
     * all written to the backing store atomically.
     *
     * @param Cache_Item_Interface $item The cache item to persist to the store.
     *
     * @return bool True if the item was successfully written to the backing
     *   store. False if a backend error prevented the write.
     *
     * @since 1.0
     */
    public function save(Cache_Item_Interface $item): bool;
    /**
     * Sets a cache item to be persisted later.
     *
     * Use this for write-behind caching when you want to accumulate multiple
     * saves and flush them to the backend in a single batch via commit(). This
     * avoids one round-trip per item when saving many items inside a loop.
     * The pool MUST call commit() automatically on destruction if there are
     * pending deferred items.
     *
     * @param Cache_Item_Interface $item The cache item to queue for deferred
     *   persistence. It will be held in memory until commit() is called.
     *
     * @return bool False if the item could not be queued or if a prior commit
     *   attempt failed. True if the item was successfully added to the queue.
     *
     * @see self::commit() Must be called to flush all deferred items.
     * @since 1.0
     */
    public function save_deferred(Cache_Item_Interface $item): bool;
    /**
     * Persists any deferred cache items.
     *
     * Flushes the deferred write queue accumulated by save_deferred() to the
     * backing store in as few backend operations as the implementation supports.
     * After a successful commit the queue is empty; after a failed commit
     * behavior regarding the remaining queue is implementation-defined.
     *
     * @return bool True if all queued items were successfully written to the
     *   backing store, or if there were no deferred items to commit. False if
     *   any item in the queue failed to persist.
     *
     * @see self::save_deferred() To queue items for deferred write.
     * @since 1.0
     */
    public function commit(): bool;
}