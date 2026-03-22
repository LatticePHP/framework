<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Execution;

use Lattice\GraphQL\Execution\GraphqlRequest;
use Lattice\GraphQL\Execution\GraphqlResponse;
use Lattice\GraphQL\Execution\QueryExecutor;
use Lattice\GraphQL\Schema\SchemaBuilder;
use Lattice\GraphQL\Tests\Fixtures\UserResolver;
use Lattice\GraphQL\Tests\Fixtures\UserType;
use PHPUnit\Framework\TestCase;

final class QueryExecutorTest extends TestCase
{
    private SchemaBuilder $builder;
    private QueryExecutor $executor;
    private UserResolver $resolver;

    protected function setUp(): void
    {
        $this->builder = new SchemaBuilder();
        $this->resolver = new UserResolver();

        $this->builder
            ->addObjectType(UserType::class)
            ->addResolver(UserResolver::class);

        $this->builder->build();

        $this->executor = new QueryExecutor($this->builder);
        $this->executor->registerResolver($this->resolver);
    }

    public function test_execute_simple_query(): void
    {
        $request = new GraphqlRequest('{ users { id name email } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertNotNull($response->data);
        $this->assertArrayHasKey('users', $response->data);
        $this->assertCount(3, $response->data['users']);
    }

    public function test_execute_query_with_keyword(): void
    {
        $request = new GraphqlRequest('query { users { id name } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertArrayHasKey('users', $response->data);
    }

    public function test_execute_query_with_arguments(): void
    {
        $request = new GraphqlRequest('{ user(id: 1) { id name email } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertNotNull($response->data);
        $this->assertArrayHasKey('user', $response->data);
        $this->assertSame(1, $response->data['user']['id']);
        $this->assertSame('Alice', $response->data['user']['name']);
        $this->assertSame('alice@example.com', $response->data['user']['email']);
    }

    public function test_execute_query_with_variables(): void
    {
        $request = new GraphqlRequest(
            'query ($userId: ID!) { user(id: $userId) { id name } }',
            ['userId' => 2],
        );
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertNotNull($response->data);
        $this->assertSame(2, $response->data['user']['id']);
        $this->assertSame('Bob', $response->data['user']['name']);
    }

    public function test_execute_query_returns_null_for_missing_entity(): void
    {
        $request = new GraphqlRequest('{ user(id: 999) { id name } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertNull($response->data['user']);
    }

    public function test_execute_mutation(): void
    {
        $request = new GraphqlRequest(
            'mutation { createUser(name: "Dave", email: "dave@example.com") { id name email } }',
        );
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertNotNull($response->data);
        $this->assertArrayHasKey('createUser', $response->data);
        $this->assertSame('Dave', $response->data['createUser']['name']);
        $this->assertSame('dave@example.com', $response->data['createUser']['email']);
    }

    public function test_execute_delete_mutation(): void
    {
        $request = new GraphqlRequest('mutation { deleteUser(id: 1) }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertTrue($response->data['deleteUser']);
    }

    public function test_execute_delete_mutation_nonexistent(): void
    {
        $request = new GraphqlRequest('mutation { deleteUser(id: 999) }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertFalse($response->data['deleteUser']);
    }

    public function test_execute_query_for_unknown_field_returns_error(): void
    {
        $request = new GraphqlRequest('{ nonexistent { id } }');
        $response = $this->executor->execute($request);

        $this->assertTrue($response->hasErrors());
        $this->assertStringContainsString('Cannot query field', $response->errors[0]['message']);
    }

    public function test_execute_empty_query_returns_error(): void
    {
        $request = new GraphqlRequest('');
        $response = $this->executor->execute($request);

        $this->assertTrue($response->hasErrors());
        $this->assertStringContainsString('Syntax error', $response->errors[0]['message']);
    }

    public function test_execute_query_with_nested_selections(): void
    {
        $request = new GraphqlRequest('{ users { name email } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertCount(3, $response->data['users']);

        $firstUser = $response->data['users'][0];
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertSame('Alice', $firstUser['name']);
    }

    public function test_execute_query_with_field_from_method(): void
    {
        $request = new GraphqlRequest('{ user(id: 1) { greeting } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertSame('Hello, Alice!', $response->data['user']['greeting']);
    }

    public function test_graphql_request_from_array(): void
    {
        $request = GraphqlRequest::fromArray([
            'query' => '{ users { id } }',
            'variables' => ['limit' => 10],
            'operationName' => 'GetUsers',
        ]);

        $this->assertSame('{ users { id } }', $request->query);
        $this->assertSame(['limit' => 10], $request->variables);
        $this->assertSame('GetUsers', $request->operationName);
    }

    public function test_graphql_request_from_array_defaults(): void
    {
        $request = GraphqlRequest::fromArray([
            'query' => '{ users { id } }',
        ]);

        $this->assertSame('{ users { id } }', $request->query);
        $this->assertSame([], $request->variables);
        $this->assertNull($request->operationName);
    }

    public function test_graphql_response_success(): void
    {
        $response = GraphqlResponse::success(['users' => []]);

        $this->assertFalse($response->hasErrors());
        $this->assertSame(['users' => []], $response->data);
        $this->assertEmpty($response->errors);
    }

    public function test_graphql_response_error(): void
    {
        $response = GraphqlResponse::error([['message' => 'Something failed']]);

        $this->assertTrue($response->hasErrors());
        $this->assertNull($response->data);
        $this->assertCount(1, $response->errors);
    }

    public function test_graphql_response_to_array(): void
    {
        $response = GraphqlResponse::success(['users' => []]);
        $array = $response->toArray();

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayNotHasKey('errors', $array);
    }

    public function test_graphql_response_to_array_with_errors(): void
    {
        $response = GraphqlResponse::error([['message' => 'Error']]);
        $array = $response->toArray();

        $this->assertArrayHasKey('errors', $array);
    }

    public function test_execute_named_query_operation(): void
    {
        $request = new GraphqlRequest(
            'query GetUsers { users { id name } }',
            [],
            'GetUsers',
        );
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertArrayHasKey('users', $response->data);
    }

    public function test_execute_with_unknown_operation_name_returns_error(): void
    {
        $request = new GraphqlRequest(
            'query GetUsers { users { id name } }',
            [],
            'NonExistent',
        );
        $response = $this->executor->execute($request);

        $this->assertTrue($response->hasErrors());
        $this->assertStringContainsString('Unknown operation', $response->errors[0]['message']);
    }

    public function test_execute_query_with_alias(): void
    {
        $request = new GraphqlRequest('{ firstUser: user(id: 1) { id name } }');
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertArrayHasKey('firstUser', $response->data);
        $this->assertSame('Alice', $response->data['firstUser']['name']);
    }

    public function test_execute_query_with_string_argument(): void
    {
        $request = new GraphqlRequest(
            'mutation { createUser(name: "Eve", email: "eve@example.com") { name } }',
        );
        $response = $this->executor->execute($request);

        $this->assertFalse($response->hasErrors());
        $this->assertSame('Eve', $response->data['createUser']['name']);
    }
}
