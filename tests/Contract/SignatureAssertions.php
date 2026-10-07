<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Shared reflection checks for the published PSR-6 interfaces.
 */
trait SignatureAssertions
{
    private function assertPublicInstanceMethod(string $class, string $name): ReflectionMethod
    {
        $method = new ReflectionMethod($class, $name);
        $this->assertTrue($method->isPublic(), $name . ' must be public');
        $this->assertFalse($method->isStatic(), $name . ' must be an instance method');
        $this->assertFalse($method->isConstructor());

        return $method;
    }

    /**
     * @param list<string> $names
     */
    private function assertMethodNames(string $class, array $names): void
    {
        $actual = [];
        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            $actual[] = $method->getName();
        }
        sort($actual);
        $expected = $names;
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    private function assertNamedReturn(ReflectionMethod $method, string $typeName, ?bool $allowsNull = null): void
    {
        $type = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type, $method->getName() . ' return type');
        $this->assertSame($typeName, $type->getName());
        if ($allowsNull !== null) {
            $this->assertSame($allowsNull, $type->allowsNull());
        }
    }

    private function assertRequiredNamedParameter(ReflectionMethod $method, int $index, string $name, string $typeName, bool $allowsNull): void
    {
        $parameter = $this->parameter($method, $index, $name, false);
        $type = $parameter->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame($typeName, $type->getName());
        $this->assertSame($allowsNull, $type->allowsNull());
    }

    /**
     * @param list<string> $typeNames
     */
    private function assertRequiredUnionParameter(ReflectionMethod $method, int $index, string $name, array $typeNames): void
    {
        $parameter = $this->parameter($method, $index, $name, false);
        $type = $parameter->getType();
        $this->assertInstanceOf(ReflectionUnionType::class, $type, $name . ' must be a union type');

        $actual = [];
        foreach ($type->getTypes() as $part) {
            $this->assertInstanceOf(ReflectionNamedType::class, $part);
            $actual[] = $part->getName();
        }
        $expected = $typeNames;
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    /**
     * @param mixed $default
     */
    private function assertOptionalNamedParameter(
        ReflectionMethod $method,
        int $index,
        string $name,
        string $typeName,
        $default
    ): void {
        $parameter = $this->parameter($method, $index, $name, true);
        $type = $parameter->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame($typeName, $type->getName());
        $this->assertFalse($type->allowsNull());
        $this->assertSame($default, $parameter->getDefaultValue());
    }

    private function parameter(ReflectionMethod $method, int $index, string $name, bool $optional): ReflectionParameter
    {
        $parameters = $method->getParameters();
        $this->assertArrayHasKey($index, $parameters);
        $parameter = $parameters[$index];
        $this->assertSame($name, $parameter->getName());
        $this->assertSame($optional, $parameter->isOptional());
        $this->assertFalse($parameter->isPassedByReference());
        $this->assertFalse($parameter->isVariadic());

        return $parameter;
    }
}
