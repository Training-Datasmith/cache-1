<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;

final class CacheItemPoolInterfaceTest extends TestCase
{
    use SignatureAssertions;

    public function testIsAnInterfaceWithThePublishedMethodsOnly(): void
    {
        $reflection = new ReflectionClass(CacheItemPoolInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertFalse($reflection->isInstantiable());
        $this->assertSame([], $reflection->getInterfaceNames());
        $this->assertSame([], $reflection->getConstants());
        $this->assertMethodNames(CacheItemPoolInterface::class, [
            'getItem',
            'getItems',
            'hasItem',
            'clear',
            'deleteItem',
            'deleteItems',
            'save',
            'saveDeferred',
            'commit',
        ]);
    }

    public function testGetItemSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'getItem');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'key', 'string', false);
        $this->assertNamedReturn($method, CacheItemInterface::class, false);
    }

    public function testGetItemsSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'getItems');

        $this->assertCount(1, $method->getParameters());
        $this->assertOptionalNamedParameter($method, 0, 'keys', 'array', []);
        $this->assertNamedReturn($method, 'iterable', false);
    }

    public function testHasItemSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'hasItem');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'key', 'string', false);
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testClearSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'clear');

        $this->assertCount(0, $method->getParameters());
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testDeleteItemSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'deleteItem');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'key', 'string', false);
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testDeleteItemsSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'deleteItems');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'keys', 'array', false);
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testSaveSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'save');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'item', CacheItemInterface::class, false);
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testSaveDeferredSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'saveDeferred');

        $this->assertCount(1, $method->getParameters());
        $this->assertRequiredNamedParameter($method, 0, 'item', CacheItemInterface::class, false);
        $this->assertNamedReturn($method, 'bool', false);
    }

    public function testCommitSignature(): void
    {
        $method = $this->assertPublicInstanceMethod(CacheItemPoolInterface::class, 'commit');

        $this->assertCount(0, $method->getParameters());
        $this->assertNamedReturn($method, 'bool', false);
    }
}
