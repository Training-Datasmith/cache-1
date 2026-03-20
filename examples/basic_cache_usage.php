<?php

declare(strict_types=1);

/**
 * Example: Basic PSR-6 cache item pool usage.
 *
 * This example demonstrates the canonical cache-aside pattern:
 *   1. Ask the pool for an item.
 *   2. Check is_hit() — if true, use the cached value.
 *   3. If miss, compute the value, set it on the item, save it.
 *
 * Replace `AcmeCachePool` with any PSR-6 compliant implementation
 * (e.g., symfony/cache, cache/filesystem-adapter).
 */

use Psr\Cache\Cache_Item_Pool_Interface;

/**
 * Fetch expensive user data, using PSR-6 cache to avoid repeated DB queries.
 *
 * @param Cache_Item_Pool_Interface $pool A PSR-6 cache pool.
 * @param int $user_id The database ID of the user to fetch.
 * @return array<string, mixed> User data array.
 */
function get_user(Cache_Item_Pool_Interface $pool, int $user_id): array
{
    $cache_key = 'user_' . $user_id;
    $item = $pool->get_item($cache_key);

    if ($item->is_hit()) {
        // Cache hit: return the stored value without touching the database.
        return $item->get();
    }

    // Cache miss: fetch from the database (simulated here).
    $user_data = [
        'id'    => $user_id,
        'name'  => 'Alice',
        'email' => 'alice@example.com',
    ];

    // Store in cache for 1 hour (3600 seconds).
    $item->set($user_data)->expires_after(3600);
    $pool->save($item);

    return $user_data;
}

// --- Deferred writes example ---

/**
 * Warm the cache for a batch of user IDs using deferred saves.
 *
 * save_deferred() queues each item in memory; commit() flushes all at once,
 * reducing round-trips to the backend from N to 1.
 *
 * @param Cache_Item_Pool_Interface $pool A PSR-6 cache pool.
 * @param array<int, array<string, mixed>> $users Map of user_id => user_data.
 */
function warm_user_cache(Cache_Item_Pool_Interface $pool, array $users): void
{
    foreach ($users as $user_id => $user_data) {
        $item = $pool->get_item('user_' . $user_id);
        $item->set($user_data)->expires_after(3600);
        $pool->save_deferred($item);  // Queued, not yet written.
    }

    $pool->commit();  // Single batched write to the backend.
}
