<?php

declare(strict_types=1);

namespace Lattice\Serializer\Tests\Unit;

use Lattice\Contracts\Serializer\SerializerInterface;
use Lattice\Serializer\PhpSerializer;
use Lattice\Serializer\SerializationException;
use Lattice\Serializer\Tests\Fixtures\SampleDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpSerializerTest extends TestCase
{
    private PhpSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PhpSerializer();
    }

    #[Test]
    public function test_implements_serializer_interface(): void
    {
        $this->assertInstanceOf(SerializerInterface::class, $this->serializer);
    }

    // --- Serialize scalars ---

    #[Test]
    public function test_serialize_string(): void
    {
        $result = $this->serializer->serialize('hello');
        $this->assertSame(\serialize('hello'), $result);
    }

    #[Test]
    public function test_serialize_int(): void
    {
        $result = $this->serializer->serialize(42);
        $this->assertSame(\serialize(42), $result);
    }

    #[Test]
    public function test_serialize_float(): void
    {
        $result = $this->serializer->serialize(3.14);
        $this->assertSame(\serialize(3.14), $result);
    }

    #[Test]
    public function test_serialize_bool(): void
    {
        $result = $this->serializer->serialize(true);
        $this->assertSame(\serialize(true), $result);
    }

    #[Test]
    public function test_serialize_array(): void
    {
        $data = ['foo' => 'bar', 'baz' => [1, 2]];
        $result = $this->serializer->serialize($data);
        $this->assertSame(\serialize($data), $result);
    }

    // --- Deserialize scalars ---

    #[Test]
    public function test_deserialize_string(): void
    {
        $serialized = \serialize('hello');
        $this->assertSame('hello', $this->serializer->deserialize($serialized, 'string'));
    }

    #[Test]
    public function test_deserialize_int(): void
    {
        $serialized = \serialize(42);
        $this->assertSame(42, $this->serializer->deserialize($serialized, 'int'));
    }

    #[Test]
    public function test_deserialize_array(): void
    {
        $data = ['key' => 'value'];
        $serialized = \serialize($data);
        $this->assertSame($data, $this->serializer->deserialize($serialized, 'array'));
    }

    // --- Deserialize objects ---

    #[Test]
    public function test_deserialize_object(): void
    {
        $dto = new SampleDto('Alice', 30, 'alice@example.com');
        $serialized = \serialize($dto);
        $result = $this->serializer->deserialize($serialized, SampleDto::class);

        $this->assertInstanceOf(SampleDto::class, $result);
        $this->assertSame('Alice', $result->name);
        $this->assertSame(30, $result->age);
    }

    // --- Round-trip ---

    #[Test]
    public function test_round_trip_array(): void
    {
        $original = ['nested' => ['data' => true]];
        $serialized = $this->serializer->serialize($original);
        $result = $this->serializer->deserialize($serialized, 'array');

        $this->assertSame($original, $result);
    }

    #[Test]
    public function test_round_trip_object(): void
    {
        $original = new SampleDto('Bob', 25);
        $serialized = $this->serializer->serialize($original);
        $result = $this->serializer->deserialize($serialized, SampleDto::class);

        $this->assertInstanceOf(SampleDto::class, $result);
        $this->assertSame('Bob', $result->name);
        $this->assertSame(25, $result->age);
        $this->assertSame('default@example.com', $result->email);
    }

    // --- Error handling ---

    #[Test]
    public function test_deserialize_invalid_data_throws(): void
    {
        $this->expectException(SerializationException::class);
        $this->serializer->deserialize('not-valid-serialized-data', 'array');
    }
}
