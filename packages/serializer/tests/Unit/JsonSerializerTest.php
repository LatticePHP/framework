<?php

declare(strict_types=1);

namespace Lattice\Serializer\Tests\Unit;

use Lattice\Contracts\Serializer\SerializerInterface;
use Lattice\Serializer\JsonSerializer;
use Lattice\Serializer\SerializationException;
use Lattice\Serializer\Tests\Fixtures\PlainObject;
use Lattice\Serializer\Tests\Fixtures\SampleDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    #[Test]
    public function test_implements_serializer_interface(): void
    {
        $this->assertInstanceOf(SerializerInterface::class, $this->serializer);
    }

    // --- Serialize ---

    #[Test]
    public function test_serialize_string(): void
    {
        $this->assertSame('"hello"', $this->serializer->serialize('hello'));
    }

    #[Test]
    public function test_serialize_int(): void
    {
        $this->assertSame('42', $this->serializer->serialize(42));
    }

    #[Test]
    public function test_serialize_float(): void
    {
        $this->assertSame('3.14', $this->serializer->serialize(3.14));
    }

    #[Test]
    public function test_serialize_bool(): void
    {
        $this->assertSame('true', $this->serializer->serialize(true));
    }

    #[Test]
    public function test_serialize_null(): void
    {
        $this->assertSame('null', $this->serializer->serialize(null));
    }

    #[Test]
    public function test_serialize_array(): void
    {
        $result = $this->serializer->serialize(['foo' => 'bar', 'baz' => 1]);
        $this->assertSame('{"foo":"bar","baz":1}', $result);
    }

    #[Test]
    public function test_serialize_indexed_array(): void
    {
        $result = $this->serializer->serialize([1, 2, 3]);
        $this->assertSame('[1,2,3]', $result);
    }

    #[Test]
    public function test_serialize_unicode(): void
    {
        $result = $this->serializer->serialize('こんにちは');
        $this->assertSame('"こんにちは"', $result);
    }

    // --- Deserialize scalars ---

    #[Test]
    public function test_deserialize_string(): void
    {
        $this->assertSame('hello', $this->serializer->deserialize('"hello"', 'string'));
    }

    #[Test]
    public function test_deserialize_int(): void
    {
        $this->assertSame(42, $this->serializer->deserialize('42', 'int'));
    }

    #[Test]
    public function test_deserialize_float(): void
    {
        $this->assertSame(3.14, $this->serializer->deserialize('3.14', 'float'));
    }

    #[Test]
    public function test_deserialize_bool(): void
    {
        $this->assertTrue($this->serializer->deserialize('true', 'bool'));
    }

    #[Test]
    public function test_deserialize_array(): void
    {
        $result = $this->serializer->deserialize('{"foo":"bar"}', 'array');
        $this->assertSame(['foo' => 'bar'], $result);
    }

    // --- Deserialize objects ---

    #[Test]
    public function test_deserialize_dto_with_constructor(): void
    {
        $json = '{"name":"Alice","age":30,"email":"alice@example.com"}';
        $dto = $this->serializer->deserialize($json, SampleDto::class);

        $this->assertInstanceOf(SampleDto::class, $dto);
        $this->assertSame('Alice', $dto->name);
        $this->assertSame(30, $dto->age);
        $this->assertSame('alice@example.com', $dto->email);
    }

    #[Test]
    public function test_deserialize_dto_uses_default_values(): void
    {
        $json = '{"name":"Bob","age":25}';
        $dto = $this->serializer->deserialize($json, SampleDto::class);

        $this->assertSame('default@example.com', $dto->email);
    }

    #[Test]
    public function test_deserialize_plain_object_without_constructor(): void
    {
        $json = '{"name":"test","value":99}';
        $obj = $this->serializer->deserialize($json, PlainObject::class);

        $this->assertInstanceOf(PlainObject::class, $obj);
        $this->assertSame('test', $obj->name);
        $this->assertSame(99, $obj->value);
    }

    // --- Round-trip ---

    #[Test]
    public function test_round_trip_array(): void
    {
        $original = ['key' => 'value', 'nested' => ['a' => 1]];
        $serialized = $this->serializer->serialize($original);
        $deserialized = $this->serializer->deserialize($serialized, 'array');

        $this->assertSame($original, $deserialized);
    }

    #[Test]
    public function test_round_trip_scalar(): void
    {
        $serialized = $this->serializer->serialize(42);
        $this->assertSame(42, $this->serializer->deserialize($serialized, 'int'));
    }

    // --- Error handling ---

    #[Test]
    public function test_deserialize_invalid_json_throws(): void
    {
        $this->expectException(SerializationException::class);
        $this->serializer->deserialize('{invalid', 'array');
    }

    #[Test]
    public function test_deserialize_nonexistent_class_throws(): void
    {
        $this->expectException(SerializationException::class);
        $this->serializer->deserialize('{"foo":"bar"}', 'NonExistent\\Class');
    }
}
