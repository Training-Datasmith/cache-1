<?php

declare (strict_types=1);
namespace Psr\Cache;

/**
 * Exception interface for invalid cache arguments.
 *
 * Any time an invalid argument is passed into a method it must throw an
 * exception class which implements Psr\Cache\InvalidArgumentException.
 * The most common trigger is a cache key that contains characters outside
 * the allowed set (A-Z, a-z, 0-9, _, .) or that exceeds the maximum
 * key length of 64 characters.
 *
 * @since 1.0
 * @see Cache_Item_Pool_Interface::get_item() Primary method that throws this.
 */
interface InvalidArgumentException extends Cache_Exception
{
}