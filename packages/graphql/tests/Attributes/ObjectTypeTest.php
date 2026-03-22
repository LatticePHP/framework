<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Attributes;

use Lattice\GraphQL\Attributes\EnumType;
use Lattice\GraphQL\Attributes\Field;
use Lattice\GraphQL\Attributes\InputType;
use Lattice\GraphQL\Attributes\ObjectType;
use Lattice\GraphQL\Tests\Fixtures\CreateUserInput;
use Lattice\GraphQL\Tests\Fixtures\UserStatusEnum;
use Lattice\GraphQL\Tests\Fixtures\UserType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ObjectTypeTest extends TestCase
{
    public function test_object_type_attribute_defaults(): void
    {
        $attr = new ObjectType();

        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
    }

    public function test_object_type_attribute_with_name(): void
    {
        $attr = new ObjectType(name: 'User');

        $this->assertSame('User', $attr->name);
        $this->assertNull($attr->description);
    }

    public function test_object_type_attribute_with_all_parameters(): void
    {
        $attr = new ObjectType(name: 'User', description: 'A system user');

        $this->assertSame('User', $attr->name);
        $this->assertSame('A system user', $attr->description);
    }

    public function test_object_type_attribute_on_class_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserType::class);
        $attrs = $reflection->getAttributes(ObjectType::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('User', $instance->name);
        $this->assertSame('A user in the system', $instance->description);
    }

    public function test_field_attribute_on_property_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserType::class);
        $idProp = $reflection->getProperty('id');
        $attrs = $idProp->getAttributes(Field::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('ID!', $instance->type);
        $this->assertSame('The user identifier', $instance->description);
    }

    public function test_field_attribute_with_deprecation(): void
    {
        $reflection = new ReflectionClass(UserType::class);
        $usernameProp = $reflection->getProperty('username');
        $attrs = $usernameProp->getAttributes(Field::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('Use email instead', $instance->deprecationReason);
    }

    public function test_field_attribute_on_method_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserType::class);
        $method = $reflection->getMethod('greeting');
        $attrs = $method->getAttributes(Field::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('String!', $instance->type);
        $this->assertSame('The full greeting', $instance->description);
    }

    public function test_field_attribute_defaults(): void
    {
        $attr = new Field();

        $this->assertNull($attr->name);
        $this->assertNull($attr->type);
        $this->assertNull($attr->description);
        $this->assertNull($attr->deprecationReason);
        $this->assertFalse($attr->nullable);
    }

    public function test_field_attribute_with_nullable(): void
    {
        $attr = new Field(nullable: true);

        $this->assertTrue($attr->nullable);
    }

    public function test_input_type_attribute_on_class_via_reflection(): void
    {
        $reflection = new ReflectionClass(CreateUserInput::class);
        $attrs = $reflection->getAttributes(InputType::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('CreateUserInput', $instance->name);
        $this->assertSame('Input for creating a user', $instance->description);
    }

    public function test_enum_type_attribute_on_enum_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserStatusEnum::class);
        $attrs = $reflection->getAttributes(EnumType::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('UserStatus', $instance->name);
        $this->assertSame('The status of a user account', $instance->description);
    }

    public function test_input_type_attribute_defaults(): void
    {
        $attr = new InputType();

        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
    }

    public function test_enum_type_attribute_defaults(): void
    {
        $attr = new EnumType();

        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
    }
}
