<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Integration;

use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Exception\BadRequestException;
use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Exception\UnauthorizedException;
use Lattice\Http\ExceptionHandler;
use Lattice\Http\ModelResolver;
use Lattice\Http\ParameterResolver;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\Tests\Fixtures\ContactFilterDto;
use Lattice\Http\Tests\Fixtures\ContactResource;
use Lattice\Http\Tests\Fixtures\CreateContactDto;
use Lattice\Routing\ParameterBinding;
use Lattice\Routing\RouteDefinition;
use Lattice\Validation\DtoMapper;
use Lattice\Validation\Exceptions\ValidationException;
use Lattice\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ParameterBindingTest extends TestCase
{
    private ParameterResolver $resolver;
    private ExceptionHandler $exceptionHandler;

    protected function setUp(): void
    {
        $this->resolver = new ParameterResolver(
            dtoMapper: new DtoMapper(),
            validator: new Validator(),
            modelResolver: new ModelResolver(),
        );
        $this->exceptionHandler = new ExceptionHandler();
    }

    // ========================================================================
    // #[Body] — DTO deserialization and validation
    // ========================================================================

    public function test_body_json_deserialized_to_dto(): void
    {
        $request = new Request('POST', '/contacts', [], [], [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'dto',
                    parameterType: CreateContactDto::class,
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertInstanceOf(CreateContactDto::class, $params['dto']);
        $this->assertSame('John', $params['dto']->first_name);
        $this->assertSame('Doe', $params['dto']->last_name);
        $this->assertSame('john@example.com', $params['dto']->email);
    }

    public function test_body_invalid_data_returns_422_with_field_errors(): void
    {
        $request = new Request('POST', '/contacts', [], [], [
            'first_name' => '',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
        ]);

        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'dto',
                    parameterType: CreateContactDto::class,
                ),
            ],
        );

        try {
            $this->resolver->resolve($request, $route, null);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $response = $this->exceptionHandler->handle($e);

            $this->assertSame(422, $response->statusCode);
            $this->assertSame('application/problem+json', $response->headers['Content-Type']);
            $this->assertSame('https://httpstatuses.io/422', $response->body['type']);
            $this->assertSame('Validation Failed', $response->body['title']);
            $this->assertSame(422, $response->body['status']);
            $this->assertArrayHasKey('errors', $response->body);

            // first_name is empty so Required rule should fire
            $this->assertArrayHasKey('first_name', $response->body['errors']);
            // email is invalid
            $this->assertArrayHasKey('email', $response->body['errors']);
        }
    }

    public function test_body_without_type_returns_raw_array(): void
    {
        $body = ['key' => 'value'];
        $request = new Request('POST', '/data', [], [], $body);

        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/data',
            controllerClass: 'DataController',
            methodName: 'store',
            parameterBindings: [
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'data',
                    parameterType: 'array',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame($body, $params['data']);
    }

    public function test_body_non_array_throws_bad_request(): void
    {
        $this->expectException(BadRequestException::class);

        $request = new Request('POST', '/contacts', [], [], 'not-json');

        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'dto',
                    parameterType: CreateContactDto::class,
                ),
            ],
        );

        $this->resolver->resolve($request, $route, null);
    }

    // ========================================================================
    // #[Query] — Type coercion and DTO mapping
    // ========================================================================

    public function test_query_primitive_int_coerced(): void
    {
        $request = new Request('GET', '/contacts', [], ['page' => '3'], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'query',
                    parameterName: 'page',
                    name: 'page',
                    parameterType: 'int',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame(3, $params['page']);
    }

    public function test_query_default_value_used_when_missing(): void
    {
        $request = new Request('GET', '/contacts', [], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'query',
                    parameterName: 'page',
                    name: 'page',
                    parameterType: 'int',
                    hasDefault: true,
                    defaultValue: 1,
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame(1, $params['page']);
    }

    public function test_query_dto_mapping(): void
    {
        $request = new Request('GET', '/contacts', [], [
            'page' => '2',
            'limit' => '25',
            'search' => 'john',
        ], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'query',
                    parameterName: 'filter',
                    parameterType: ContactFilterDto::class,
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertInstanceOf(ContactFilterDto::class, $params['filter']);
        $this->assertSame(2, $params['filter']->page);
        $this->assertSame(25, $params['filter']->limit);
        $this->assertSame('john', $params['filter']->search);
    }

    public function test_query_returns_null_when_missing_and_no_default(): void
    {
        $request = new Request('GET', '/contacts', [], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'query',
                    parameterName: 'page',
                    name: 'page',
                    parameterType: 'int',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertNull($params['page']);
    }

    // ========================================================================
    // #[Param] — Route parameters with type coercion
    // ========================================================================

    public function test_param_int_coerced(): void
    {
        $request = new Request('GET', '/contacts/42', [], [], null, ['id' => '42']);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts/{id}',
            controllerClass: 'ContactController',
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(
                    type: 'param',
                    parameterName: 'id',
                    name: 'id',
                    parameterType: 'int',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame(42, $params['id']);
    }

    public function test_param_string_returned_without_type(): void
    {
        $request = new Request('GET', '/contacts/abc', [], [], null, ['slug' => 'abc']);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts/{slug}',
            controllerClass: 'ContactController',
            methodName: 'showBySlug',
            parameterBindings: [
                new ParameterBinding(
                    type: 'param',
                    parameterName: 'slug',
                    name: 'slug',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('abc', $params['slug']);
    }

    public function test_param_model_binding_throws_bad_request_for_non_model(): void
    {
        $this->expectException(BadRequestException::class);

        $request = new Request('GET', '/items/1', [], [], null, ['id' => '1']);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/items/{id}',
            controllerClass: 'ItemController',
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(
                    type: 'param',
                    parameterName: 'item',
                    name: 'id',
                    parameterType: \stdClass::class,
                ),
            ],
        );

        $this->resolver->resolve($request, $route, null);
    }

    // ========================================================================
    // #[Header] — Header extraction
    // ========================================================================

    public function test_header_extracted(): void
    {
        $request = new Request('GET', '/contacts', ['X-Request-Id' => 'req-abc-123'], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'header',
                    parameterName: 'requestId',
                    name: 'X-Request-Id',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('req-abc-123', $params['requestId']);
    }

    public function test_header_default_used_when_missing(): void
    {
        $request = new Request('GET', '/contacts', [], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'index',
            parameterBindings: [
                new ParameterBinding(
                    type: 'header',
                    parameterName: 'requestId',
                    name: 'X-Request-Id',
                    hasDefault: true,
                    defaultValue: 'default-id',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, null);

        $this->assertSame('default-id', $params['requestId']);
    }

    // ========================================================================
    // #[CurrentUser] — Auth principal
    // ========================================================================

    public function test_current_user_resolved_from_principal(): void
    {
        $principal = $this->createMock(PrincipalInterface::class);
        $principal->method('getId')->willReturn(1);

        $request = new Request('GET', '/me', [], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/me',
            controllerClass: 'ProfileController',
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(
                    type: 'current_user',
                    parameterName: 'user',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, $principal);

        $this->assertSame($principal, $params['user']);
    }

    public function test_current_user_throws_401_when_no_auth(): void
    {
        $request = new Request('GET', '/me', [], [], null);

        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/me',
            controllerClass: 'ProfileController',
            methodName: 'show',
            parameterBindings: [
                new ParameterBinding(
                    type: 'current_user',
                    parameterName: 'user',
                ),
            ],
        );

        try {
            $this->resolver->resolve($request, $route, null);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $response = $this->exceptionHandler->handle($e);
            $this->assertSame(401, $response->statusCode);
        }
    }

    // ========================================================================
    // Auto-serialization (HttpKernel serializeResult via direct tests)
    // ========================================================================

    public function test_array_return_produces_json_response(): void
    {
        $result = ['id' => 1, 'name' => 'John'];
        $response = Response::json($result);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame($result, $response->body);
    }

    public function test_resource_return_produces_json_from_to_array(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $resource = new ContactResource($data);
        $response = Response::json($resource->toArray());

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $response->body);
    }

    public function test_null_return_produces_204_no_content(): void
    {
        $response = Response::noContent();

        $this->assertSame(204, $response->statusCode);
        $this->assertNull($response->body);
    }

    // ========================================================================
    // Validation error format (RFC 9457)
    // ========================================================================

    public function test_validation_exception_produces_rfc9457_response(): void
    {
        $request = new Request('POST', '/contacts', [], [], [
            'first_name' => '',
            'last_name' => '',
            'email' => 'invalid',
        ]);

        $route = new RouteDefinition(
            httpMethod: 'POST',
            path: '/contacts',
            controllerClass: 'ContactController',
            methodName: 'create',
            parameterBindings: [
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'dto',
                    parameterType: CreateContactDto::class,
                ),
            ],
        );

        try {
            $this->resolver->resolve($request, $route, null);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $response = $this->exceptionHandler->handle($e);

            $this->assertSame(422, $response->statusCode);
            $this->assertSame('application/problem+json', $response->headers['Content-Type']);

            $body = $response->body;
            $this->assertSame('https://httpstatuses.io/422', $body['type']);
            $this->assertSame('Validation Failed', $body['title']);
            $this->assertSame(422, $body['status']);
            $this->assertSame('The given data was invalid.', $body['detail']);
            $this->assertIsArray($body['errors']);

            // Errors should be grouped by field with array of messages
            foreach ($body['errors'] as $field => $messages) {
                $this->assertIsString($field);
                $this->assertIsArray($messages);
                foreach ($messages as $message) {
                    $this->assertIsString($message);
                }
            }
        }
    }

    // ========================================================================
    // Multiple bindings in one route
    // ========================================================================

    public function test_multiple_binding_types_resolved_together(): void
    {
        $principal = $this->createMock(PrincipalInterface::class);

        $request = new Request(
            'PUT',
            '/contacts/42',
            ['X-Request-Id' => 'req-1'],
            ['include' => 'addresses'],
            ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com'],
            ['id' => '42'],
        );

        $route = new RouteDefinition(
            httpMethod: 'PUT',
            path: '/contacts/{id}',
            controllerClass: 'ContactController',
            methodName: 'update',
            parameterBindings: [
                new ParameterBinding(
                    type: 'param',
                    parameterName: 'id',
                    name: 'id',
                    parameterType: 'int',
                ),
                new ParameterBinding(
                    type: 'body',
                    parameterName: 'dto',
                    parameterType: CreateContactDto::class,
                ),
                new ParameterBinding(
                    type: 'query',
                    parameterName: 'include',
                    name: 'include',
                    parameterType: 'string',
                ),
                new ParameterBinding(
                    type: 'header',
                    parameterName: 'requestId',
                    name: 'X-Request-Id',
                ),
                new ParameterBinding(
                    type: 'current_user',
                    parameterName: 'user',
                ),
            ],
        );

        $params = $this->resolver->resolve($request, $route, $principal);

        $this->assertSame(42, $params['id']);
        $this->assertInstanceOf(CreateContactDto::class, $params['dto']);
        $this->assertSame('Jane', $params['dto']->first_name);
        $this->assertSame('addresses', $params['include']);
        $this->assertSame('req-1', $params['requestId']);
        $this->assertSame($principal, $params['user']);
    }
}
