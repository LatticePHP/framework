<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Schema;

use Lattice\GraphQL\Schema\SchemaBuilder;
use Lattice\GraphQL\Tests\Fixtures\CreateUserInput;
use Lattice\GraphQL\Tests\Fixtures\UserResolver;
use Lattice\GraphQL\Tests\Fixtures\UserStatusEnum;
use Lattice\GraphQL\Tests\Fixtures\UserType;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SchemaBuilder();
    }

    public function test_build_empty_schema(): void
    {
        $schema = $this->builder->build();

        $this->assertArrayHasKey('types', $schema);
        $this->assertArrayHasKey('inputTypes', $schema);
        $this->assertArrayHasKey('enumTypes', $schema);
        $this->assertArrayHasKey('queries', $schema);
        $this->assertArrayHasKey('mutations', $schema);
        $this->assertEmpty($schema['types']);
        $this->assertEmpty($schema['queries']);
    }

    public function test_build_with_object_type(): void
    {
        $this->builder->addObjectType(UserType::class);
        $schema = $this->builder->build();

        $this->assertArrayHasKey('User', $schema['types']);

        $userType = $schema['types']['User'];
        $this->assertSame('A user in the system', $userType['description']);
        $this->assertArrayHasKey('fields', $userType);

        $fields = $userType['fields'];
        // Should have: id, email, name, displayName, username, greeting
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('displayName', $fields);
        $this->assertArrayHasKey('username', $fields);
        $this->assertArrayHasKey('greeting', $fields);
    }

    public function test_object_type_field_types_are_inferred(): void
    {
        $this->builder->addObjectType(UserType::class);
        $schema = $this->builder->build();

        $fields = $schema['types']['User']['fields'];

        // id has explicit type override ID!
        $this->assertSame('ID!', $fields['id']['type']);

        // email: string -> String! (non-null)
        $this->assertSame('String!', $fields['email']['type']);

        // name: string -> String! (non-null)
        $this->assertSame('String!', $fields['name']['type']);

        // username: ?string -> String (nullable)
        $this->assertSame('String', $fields['username']['type']);

        // greeting: explicit type String!
        $this->assertSame('String!', $fields['greeting']['type']);
    }

    public function test_object_type_field_descriptions(): void
    {
        $this->builder->addObjectType(UserType::class);
        $schema = $this->builder->build();

        $fields = $schema['types']['User']['fields'];

        $this->assertSame('The user identifier', $fields['id']['description']);
        $this->assertSame('The user email address', $fields['email']['description']);
        $this->assertSame('Formatted display name', $fields['displayName']['description']);
    }

    public function test_object_type_field_deprecation(): void
    {
        $this->builder->addObjectType(UserType::class);
        $schema = $this->builder->build();

        $fields = $schema['types']['User']['fields'];

        $this->assertSame('Use email instead', $fields['username']['deprecationReason']);
        $this->assertNull($fields['email']['deprecationReason']);
    }

    public function test_build_with_enum_type(): void
    {
        $this->builder->addEnumType(UserStatusEnum::class);
        $schema = $this->builder->build();

        $this->assertArrayHasKey('UserStatus', $schema['enumTypes']);

        $enumType = $schema['enumTypes']['UserStatus'];
        $this->assertSame('The status of a user account', $enumType['description']);
        $this->assertArrayHasKey('values', $enumType);

        $values = $enumType['values'];
        $this->assertArrayHasKey('Active', $values);
        $this->assertArrayHasKey('Inactive', $values);
        $this->assertArrayHasKey('Banned', $values);
        $this->assertArrayHasKey('Pending', $values);
    }

    public function test_build_with_input_type(): void
    {
        $this->builder->addInputType(CreateUserInput::class);
        $schema = $this->builder->build();

        $this->assertArrayHasKey('CreateUserInput', $schema['inputTypes']);

        $inputType = $schema['inputTypes']['CreateUserInput'];
        $this->assertSame('Input for creating a user', $inputType['description']);

        $fields = $inputType['fields'];
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('username', $fields);
    }

    public function test_build_with_resolver_discovers_queries(): void
    {
        $this->builder->addResolver(UserResolver::class);
        $schema = $this->builder->build();

        $this->assertArrayHasKey('users', $schema['queries']);
        $this->assertArrayHasKey('user', $schema['queries']);

        $usersQuery = $schema['queries']['users'];
        $this->assertSame(UserResolver::class, $usersQuery['class']);
        $this->assertSame('getUsers', $usersQuery['method']);
        $this->assertSame('Get all users', $usersQuery['description']);
    }

    public function test_build_with_resolver_discovers_mutations(): void
    {
        $this->builder->addResolver(UserResolver::class);
        $schema = $this->builder->build();

        $this->assertArrayHasKey('createUser', $schema['mutations']);
        $this->assertArrayHasKey('deleteUser', $schema['mutations']);

        $createMutation = $schema['mutations']['createUser'];
        $this->assertSame(UserResolver::class, $createMutation['class']);
        $this->assertSame('createUser', $createMutation['method']);
        $this->assertSame('Create a new user', $createMutation['description']);
    }

    public function test_query_arguments_are_extracted(): void
    {
        $this->builder->addResolver(UserResolver::class);
        $schema = $this->builder->build();

        $userQuery = $schema['queries']['user'];
        $this->assertNotEmpty($userQuery['arguments']);

        // The #[Argument] attribute overrides the name to 'id' with type 'ID!'
        $this->assertArrayHasKey('id', $userQuery['arguments']);
        $this->assertSame('ID!', $userQuery['arguments']['id']['type']);
        $this->assertSame('The user ID', $userQuery['arguments']['id']['description']);
    }

    public function test_mutation_arguments_are_extracted(): void
    {
        $this->builder->addResolver(UserResolver::class);
        $schema = $this->builder->build();

        $createMutation = $schema['mutations']['createUser'];
        $this->assertArrayHasKey('name', $createMutation['arguments']);
        $this->assertArrayHasKey('email', $createMutation['arguments']);

        $this->assertSame('String!', $createMutation['arguments']['name']['type']);
        $this->assertSame('String!', $createMutation['arguments']['email']['type']);
    }

    public function test_to_sdl_generates_valid_output(): void
    {
        $this->builder->addObjectType(UserType::class);
        $this->builder->addEnumType(UserStatusEnum::class);
        $this->builder->addInputType(CreateUserInput::class);
        $this->builder->addResolver(UserResolver::class);
        $this->builder->build();

        $sdl = $this->builder->toSDL();

        $this->assertStringContainsString('type User', $sdl);
        $this->assertStringContainsString('enum UserStatus', $sdl);
        $this->assertStringContainsString('input CreateUserInput', $sdl);
        $this->assertStringContainsString('type Query', $sdl);
        $this->assertStringContainsString('type Mutation', $sdl);
        $this->assertStringContainsString('users', $sdl);
        $this->assertStringContainsString('createUser', $sdl);
    }

    public function test_to_sdl_includes_descriptions(): void
    {
        $this->builder->addObjectType(UserType::class);
        $this->builder->build();

        $sdl = $this->builder->toSDL();

        $this->assertStringContainsString('A user in the system', $sdl);
        $this->assertStringContainsString('The user identifier', $sdl);
    }

    public function test_to_sdl_includes_deprecation(): void
    {
        $this->builder->addObjectType(UserType::class);
        $this->builder->build();

        $sdl = $this->builder->toSDL();

        $this->assertStringContainsString('@deprecated(reason: "Use email instead")', $sdl);
    }

    public function test_get_type_registry(): void
    {
        $registry = $this->builder->getTypeRegistry();

        $this->assertNotNull($registry);
    }

    public function test_build_full_schema(): void
    {
        $this->builder->addObjectType(UserType::class);
        $this->builder->addEnumType(UserStatusEnum::class);
        $this->builder->addInputType(CreateUserInput::class);
        $this->builder->addResolver(UserResolver::class);

        $schema = $this->builder->build();

        // Verify all pieces are present
        $this->assertNotEmpty($schema['types']);
        $this->assertNotEmpty($schema['enumTypes']);
        $this->assertNotEmpty($schema['inputTypes']);
        $this->assertNotEmpty($schema['queries']);
        $this->assertNotEmpty($schema['mutations']);
    }

    public function test_fluent_interface(): void
    {
        $result = $this->builder
            ->addObjectType(UserType::class)
            ->addEnumType(UserStatusEnum::class)
            ->addInputType(CreateUserInput::class)
            ->addResolver(UserResolver::class);

        $this->assertSame($this->builder, $result);
    }
}
