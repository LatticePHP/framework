<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Attributes;

use Lattice\GraphQL\Attributes\Argument;
use Lattice\GraphQL\Attributes\Mutation;
use Lattice\GraphQL\Attributes\Query;
use Lattice\GraphQL\Tests\Fixtures\UserResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class QueryTest extends TestCase
{
    public function test_query_attribute_defaults(): void
    {
        $attr = new Query();

        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
        $this->assertNull($attr->deprecationReason);
    }

    public function test_query_attribute_with_all_parameters(): void
    {
        $attr = new Query(
            name: 'getUsers',
            description: 'Fetch all users',
            deprecationReason: 'Use listUsers instead',
        );

        $this->assertSame('getUsers', $attr->name);
        $this->assertSame('Fetch all users', $attr->description);
        $this->assertSame('Use listUsers instead', $attr->deprecationReason);
    }

    public function test_mutation_attribute_defaults(): void
    {
        $attr = new Mutation();

        $this->assertNull($attr->name);
        $this->assertNull($attr->description);
    }

    public function test_mutation_attribute_with_parameters(): void
    {
        $attr = new Mutation(name: 'createUser', description: 'Creates a user');

        $this->assertSame('createUser', $attr->name);
        $this->assertSame('Creates a user', $attr->description);
    }

    public function test_query_attribute_on_method_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserResolver::class);
        $method = $reflection->getMethod('getUsers');
        $attrs = $method->getAttributes(Query::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('users', $instance->name);
        $this->assertSame('Get all users', $instance->description);
    }

    public function test_mutation_attribute_on_method_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserResolver::class);
        $method = $reflection->getMethod('createUser');
        $attrs = $method->getAttributes(Mutation::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('createUser', $instance->name);
        $this->assertSame('Create a new user', $instance->description);
    }

    public function test_argument_attribute_on_parameter_via_reflection(): void
    {
        $reflection = new ReflectionClass(UserResolver::class);
        $method = $reflection->getMethod('getUser');
        $params = $method->getParameters();

        $this->assertCount(1, $params);

        $attrs = $params[0]->getAttributes(Argument::class);
        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('id', $instance->name);
        $this->assertSame('ID!', $instance->type);
        $this->assertSame('The user ID', $instance->description);
    }

    public function test_argument_attribute_defaults(): void
    {
        $attr = new Argument();

        $this->assertNull($attr->name);
        $this->assertNull($attr->type);
        $this->assertNull($attr->description);
        $this->assertNull($attr->defaultValue);
    }

    public function test_argument_attribute_with_default_value(): void
    {
        $attr = new Argument(
            name: 'limit',
            type: 'Int',
            description: 'Max results',
            defaultValue: 10,
        );

        $this->assertSame('limit', $attr->name);
        $this->assertSame('Int', $attr->type);
        $this->assertSame('Max results', $attr->description);
        $this->assertSame(10, $attr->defaultValue);
    }

    public function test_resolver_has_multiple_query_methods(): void
    {
        $reflection = new ReflectionClass(UserResolver::class);
        $queryMethods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->getAttributes(Query::class))) {
                $queryMethods[] = $method->getName();
            }
        }

        $this->assertContains('getUsers', $queryMethods);
        $this->assertContains('getUser', $queryMethods);
        $this->assertCount(2, $queryMethods);
    }

    public function test_resolver_has_multiple_mutation_methods(): void
    {
        $reflection = new ReflectionClass(UserResolver::class);
        $mutationMethods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->getAttributes(Mutation::class))) {
                $mutationMethods[] = $method->getName();
            }
        }

        $this->assertContains('createUser', $mutationMethods);
        $this->assertContains('deleteUser', $mutationMethods);
        $this->assertCount(2, $mutationMethods);
    }
}
