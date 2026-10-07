<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use Throwable;

final class CacheExceptionTest extends TestCase
{
    public function testCacheExceptionIsAThrowableInterface(): void
    {
        $reflection = new ReflectionClass(CacheException::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertFalse($reflection->isInstantiable());
        $this->assertSame([], $this->methodsDeclaredBy($reflection));
        $this->assertSame([], $reflection->getConstants());
        $this->assertContains(Throwable::class, $reflection->getInterfaceNames());
    }

    public function testInvalidArgumentExceptionExtendsCacheException(): void
    {
        $reflection = new ReflectionClass(InvalidArgumentException::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertFalse($reflection->isInstantiable());
        $this->assertSame([], $this->methodsDeclaredBy($reflection));
        $this->assertSame([], $reflection->getConstants());
        $this->assertContains(CacheException::class, $reflection->getInterfaceNames());
        $this->assertTrue(is_subclass_of(InvalidArgumentException::class, CacheException::class));
        $this->assertTrue(is_subclass_of(InvalidArgumentException::class, Throwable::class));
    }

    public function testUserlandInvalidArgumentExceptionCanBeCaughtAsCacheException(): void
    {
        $exception = new class('Cache key "" is not legal.') extends \InvalidArgumentException implements InvalidArgumentException {
        };

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(CacheException::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);

        try {
            throw $exception;
        } catch (CacheException $caught) {
            $this->assertSame('Cache key "" is not legal.', $caught->getMessage());
            $this->assertInstanceOf(InvalidArgumentException::class, $caught);
        }
    }

    /**
     * @return list<string>
     */
    private function methodsDeclaredBy(ReflectionClass $reflection): array
    {
        $names = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $names[] = $method->getName();
            }
        }

        return $names;
    }
}
