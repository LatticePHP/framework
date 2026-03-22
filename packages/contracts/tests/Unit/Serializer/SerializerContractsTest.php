<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Serializer;

use Lattice\Contracts\Serializer\SerializerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SerializerContractsTest extends TestCase
{
    #[Test]
    public function serializerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(SerializerInterface::class));
    }

    #[Test]
    public function serializerInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(SerializerInterface::class);

        $this->assertTrue($reflection->hasMethod('serialize'));
        $this->assertTrue($reflection->hasMethod('deserialize'));

        $this->assertSame('string', $reflection->getMethod('serialize')->getReturnType()->getName());
        $this->assertSame('mixed', $reflection->getMethod('deserialize')->getReturnType()->getName());

        $deserializeParams = $reflection->getMethod('deserialize')->getParameters();
        $this->assertCount(2, $deserializeParams);
        $this->assertSame('string', $deserializeParams[0]->getType()->getName());
        $this->assertSame('string', $deserializeParams[1]->getType()->getName());
    }

    #[Test]
    public function serializerCanBeImplemented(): void
    {
        $serializer = new class implements SerializerInterface {
            public function serialize(mixed $data): string { return json_encode($data); }
            public function deserialize(string $data, string $type): mixed { return json_decode($data, true); }
        };

        $json = $serializer->serialize(['key' => 'value']);
        $this->assertSame('{"key":"value"}', $json);

        $result = $serializer->deserialize($json, 'array');
        $this->assertSame(['key' => 'value'], $result);
    }
}
