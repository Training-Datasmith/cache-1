# Architecture: psr/cache (PSR-6)

## Purpose

This package defines PSR-6: Caching Interface. It provides a standardised contract
for full-featured cache item pools that support explicit item objects with TTL
control, deferred writes, and batch operations.

## PSR Standard

**PSR-6** — https://www.php-fig.org/psr/psr-6/

PSR-6 is designed for use cases that need fine-grained control over individual
cache entries (checking hit/miss, setting per-item TTL, queuing deferred writes).
For a simpler key/value cache API, see PSR-16 (psr/simple-cache).

## Directory Structure

```
src/
  Cache_Item_Interface.php         — Value object representing one cached entry
  Cache_Item_Pool_Interface.php    — The cache backend; creates and manages items
  Cache_Exception.php              — Marker interface for all cache exceptions
  Invalid_Argument_Exception.php   — Thrown for illegal cache keys or arguments
```

## Key Design Decisions

### Item-pool separation
The pool (`Cache_Item_Pool_Interface`) and the item (`Cache_Item_Interface`) are
separate interfaces. The pool is the factory and store; the item carries the key,
value, TTL, and hit/miss state. Callers never instantiate items directly.

### Cache miss is not an error
`get_item()` always returns a `Cache_Item_Interface`, even for missing keys. The
item's `is_hit()` method distinguishes a hit from a miss. This avoids null checks
and allows the caller to populate and save the item in the same flow.

### Deferred writes
`save_deferred()` / `commit()` allow write-behind batching. Accumulate many items
in memory, then flush them to the backend in a single round-trip. The pool MUST
auto-commit on destruction to prevent data loss.

### Immutable-style TTL on the item
TTL is set on the item (not on the pool) using `expires_at()` or `expires_after()`,
giving each item an independent expiry regardless of pool-level defaults.

## Extension Points

- Implement `Cache_Item_Pool_Interface` to add a new backend (Redis, Memcached, APCu, filesystem, etc.).
- Implement `Cache_Item_Interface` to carry additional metadata (tags, hit counters, etc.).
- Implement `Cache_Exception` / `Invalid_Argument_Exception` for domain-specific error context.

## Dependency Flow

```
Calling code
    └── Cache_Item_Pool_Interface  (injected as dependency)
            └── Cache_Item_Interface  (returned by pool methods)
```

The calling code depends only on the interfaces. Concrete implementations are
wired together by a dependency injection container or factory.
