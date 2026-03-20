<?php

declare (strict_types=1);
namespace Psr\Cache;

/**
 * Marker interface for all exceptions thrown by a PSR-6 implementing library.
 *
 * Catching this interface allows consumers to handle every cache-related
 * error with a single catch block, regardless of the specific failure type.
 * All pool and item exceptions MUST implement this interface so that
 * calling code can distinguish cache errors from unrelated application errors.
 *
 * @since 1.0
 * @see https://www.php-fig.org/psr/psr-6/
 */
interface Cache_Exception extends \Throwable
{
}