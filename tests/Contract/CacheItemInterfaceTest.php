<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use ReflectionClass;

final class CacheItemInterfaceTest extends TestCase
{
    use SignatureAssertions;

    public function testIsAnInterfaceWithThePublishedMethodsOnly(): void
    {
        $reflection = new ReflectionClass(CacheItemInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertFalse($reflection->isInstantiable());
        $this->assertSame([], $reflection->getInterfaceNames());
        $this->assertSame([], $reflection->getConstants());
        $this->assertMethodNames(CacheItemInterface::class, [
            'getKey',
            'get',
            'isHit',
            'set',
            'expiresAt',
            'expiresAfter',
        ]);
    }

    public function testGetKeySignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'getKey');

        $this->assertCount(0, $method->getParameters());
        $this->assertNamedReturn($method, 'string', false);
    }

    public function testGetSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'get');

        $this->assertCount(0, $method->getParameters());
        // mixed includes null, so ReflectionNamedType::allowsNull() is true.
        $this->assertNamedReturn($method, 'mixed', true);
    }

    public function testIsHitSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'isHit');

        $this->assertCount(0, $method->getParameters());
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testSetSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'set');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'value', 'mixed', true);
        $this->assertNamedReturn($method, 'static', false);
    }

    public function testExpiresAtSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'expiresAt');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'expiration', \DateTimeInterface::class, true);
        $this->assertNamedReturn($method, 'static', false);
    }

    public function testExpiresAfterSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemInterface::class, 'expiresAfter');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredUnionParameter($method, 0, 'time', ['int', \DateInterval::class, 'null']);
        $this->assertNamedReturn($method, 'static', false);
    }
}
