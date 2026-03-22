<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Schema;

use Lattice\GraphQL\Schema\TypeRegistry;
use PHPUnit\Framework\TestCase;

final class TypeRegistryTest extends TestCase
{
    private TypeRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new TypeRegistry();
    }

    public function test_map_string_type(): void
    {
        $result = $this->registry->mapScalarType('string');
        $this->assertSame('String', $result);
    }

    public function test_map_int_type(): void
    {
        $result = $this->registry->mapScalarType('int');
        $this->assertSame('Int', $result);
    }

    public function test_map_float_type(): void
    {
        $result = $this->registry->mapScalarType('float');
        $this->assertSame('Float', $result);
    }

    public function test_map_bool_type(): void
    {
        $result = $this->registry->mapScalarType('bool');
        $this->assertSame('Boolean', $result);
    }

    public function test_map_array_type(): void
    {
        $result = $this->registry->mapScalarType('array');
        $this->assertSame('[String]', $result);
    }

    public function test_map_null_reflection_type_returns_string(): void
    {
        $result = $this->registry->mapPhpType(null);
        $this->assertSame('String', $result);
    }

    public function test_map_non_null_string_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (string $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('String!', $result);
    }

    public function test_map_nullable_string_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (?string $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('String', $result);
    }

    public function test_map_non_null_int_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (int $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('Int!', $result);
    }

    public function test_map_nullable_int_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (?int $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('Int', $result);
    }

    public function test_map_non_null_float_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (float $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('Float!', $result);
    }

    public function test_map_non_null_bool_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (bool $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('Boolean!', $result);
    }

    public function test_map_nullable_bool_via_reflection(): void
    {
        $reflection = new \ReflectionFunction(function (?bool $x): void {});
        $param = $reflection->getParameters()[0];
        $result = $this->registry->mapPhpType($param->getType());

        $this->assertSame('Boolean', $result);
    }

    public function test_parse_type_string_simple(): void
    {
        $result = $this->registry->parseTypeString('String');

        $this->assertSame('String', $result['type']);
        $this->assertFalse($result['nonNull']);
        $this->assertFalse($result['list']);
    }

    public function test_parse_type_string_non_null(): void
    {
        $result = $this->registry->parseTypeString('String!');

        $this->assertSame('String', $result['type']);
        $this->assertTrue($result['nonNull']);
        $this->assertFalse($result['list']);
    }

    public function test_parse_type_string_list(): void
    {
        $result = $this->registry->parseTypeString('[String]');

        $this->assertSame('String', $result['type']);
        $this->assertFalse($result['nonNull']);
        $this->assertTrue($result['list']);
        $this->assertFalse($result['listItemNonNull']);
    }

    public function test_parse_type_string_non_null_list_with_non_null_items(): void
    {
        $result = $this->registry->parseTypeString('[String!]!');

        $this->assertSame('String', $result['type']);
        $this->assertTrue($result['nonNull']);
        $this->assertTrue($result['list']);
        $this->assertTrue($result['listItemNonNull']);
    }

    public function test_register_and_retrieve_object_type(): void
    {
        $this->registry->registerObjectType('User', [
            'id' => ['type' => 'ID!'],
            'name' => ['type' => 'String!'],
        ], 'A user');

        $this->assertTrue($this->registry->hasObjectType('User'));
        $this->assertFalse($this->registry->hasObjectType('Post'));

        $type = $this->registry->getObjectType('User');
        $this->assertNotNull($type);
        $this->assertSame('A user', $type['description']);
        $this->assertArrayHasKey('id', $type['fields']);
        $this->assertArrayHasKey('name', $type['fields']);
    }

    public function test_register_and_retrieve_input_type(): void
    {
        $this->registry->registerInputType('CreateUserInput', [
            'name' => ['type' => 'String!'],
            'email' => ['type' => 'String!'],
        ]);

        $this->assertTrue($this->registry->hasInputType('CreateUserInput'));
        $this->assertNotNull($this->registry->getInputType('CreateUserInput'));
    }

    public function test_register_and_retrieve_enum_type(): void
    {
        $this->registry->registerEnumType('UserStatus', [
            'ACTIVE' => '',
            'INACTIVE' => '',
        ], 'User status');

        $this->assertTrue($this->registry->hasEnumType('UserStatus'));
        $this->assertNotNull($this->registry->getEnumType('UserStatus'));
    }

    public function test_get_nonexistent_type_returns_null(): void
    {
        $this->assertNull($this->registry->getObjectType('NonExistent'));
        $this->assertNull($this->registry->getInputType('NonExistent'));
        $this->assertNull($this->registry->getEnumType('NonExistent'));
    }

    public function test_get_all_object_types(): void
    {
        $this->registry->registerObjectType('User', ['id' => ['type' => 'ID!']]);
        $this->registry->registerObjectType('Post', ['title' => ['type' => 'String!']]);

        $all = $this->registry->getObjectTypes();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('User', $all);
        $this->assertArrayHasKey('Post', $all);
    }

    public function test_custom_type_resolved_from_class_name(): void
    {
        $this->registry->registerObjectType('UserType', ['id' => ['type' => 'ID!']]);

        $result = $this->registry->mapScalarType('Some\\Namespace\\UserType');
        $this->assertSame('UserType', $result);
    }
}
