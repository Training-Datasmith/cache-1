<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixture;

/**
 * Concrete PSR-6 invalid-argument exception used by the reference pool.
 */
final class InvalidCacheArgumentException extends \InvalidArgumentException implements \Psr\Cache\InvalidArgumentException
{
}
