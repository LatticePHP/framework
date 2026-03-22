<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration;

use Lattice\Core\Application;
use Lattice\Core\Http\RequestFactory;
use Lattice\Core\Http\ResponseEmitter;
use Lattice\Http\Request;
use Lattice\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestLifecycleTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lattice-lifecycle-test-' . uniqid();
        mkdir($this->basePath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            @rmdir($this->basePath);
        }
    }

    // ─── RequestFactory ─────────────────────────────────────────────

    #[Test]
    public function test_request_factory_create_builds_request_with_all_fields(): void
    {
        $request = RequestFactory::create(
            method: 'POST',
            uri: '/users',
            headers: ['content-type' => 'application/json'],
            query: ['page' => '1'],
            body: ['name' => 'Alice'],
        );

        $this->assertSame('POST', $request->method);
        $this->assertSame('/users', $request->uri);
        $this->assertSame('application/json', $request->getHeader('content-type'));
        $this->assertSame('1', $request->getQuery('page'));
        $this->assertSame(['name' => 'Alice'], $request->getBody());
    }

    #[Test]
    public function test_request_factory_create_defaults_to_get_root(): void
    {
        $request = RequestFactory::create();

        $this->assertSame('GET', $request->method);
        $this->assertSame('/', $request->uri);
        $this->assertSame([], $request->headers);
        $this->assertSame([], $request->query);
        $this->assertNull($request->body);
        $this->assertSame([], $request->pathParams);
    }

    #[Test]
    public function test_request_factory_create_uppercases_method(): void
    {
        $request = RequestFactory::create(method: 'post');

        $this->assertSame('POST', $request->method);
    }

    // ─── ResponseEmitter ────────────────────────────────────────────

    #[Test]
    public function test_response_emitter_outputs_json_body(): void
    {
        $response = Response::json(['status' => 'ok']);

        ob_start();
        @ResponseEmitter::emit($response);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertSame(['status' => 'ok'], $decoded);
    }

    #[Test]
    public function test_response_emitter_outputs_string_body(): void
    {
        $response = new Response(
            statusCode: 200,
            headers: ['Content-Type' => 'text/plain'],
            body: 'Hello world',
        );

        ob_start();
        @ResponseEmitter::emit($response);
        $output = ob_get_clean();

        $this->assertSame('Hello world', $output);
    }

    #[Test]
    public function test_response_emitter_empty_body_outputs_nothing(): void
    {
        $response = Response::noContent();

        ob_start();
        @ResponseEmitter::emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    // ─── Request convenience methods ────────────────────────────────

    #[Test]
    public function test_request_get_method_and_uri(): void
    {
        $request = new Request(method: 'DELETE', uri: '/items/42');

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/items/42', $request->getUri());
    }

    #[Test]
    public function test_request_json_with_key(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/',
            body: ['name' => 'Alice', 'age' => 30],
        );

        $this->assertSame('Alice', $request->json('name'));
        $this->assertSame(30, $request->json('age'));
        $this->assertNull($request->json('missing'));
        $this->assertSame('fallback', $request->json('missing', 'fallback'));
    }

    #[Test]
    public function test_request_json_without_key_returns_full_body(): void
    {
        $body = ['a' => 1, 'b' => 2];
        $request = new Request(method: 'POST', uri: '/', body: $body);

        $this->assertSame($body, $request->json());
    }

    #[Test]
    public function test_request_bearer_token(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['Authorization' => 'Bearer abc123xyz'],
        );

        $this->assertSame('abc123xyz', $request->bearerToken());
    }

    #[Test]
    public function test_request_bearer_token_returns_null_when_missing(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->bearerToken());
    }

    #[Test]
    public function test_request_bearer_token_returns_null_for_non_bearer(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['Authorization' => 'Basic dXNlcjpwYXNz'],
        );

        $this->assertNull($request->bearerToken());
    }

    // ─── Response convenience methods ───────────────────────────────

    #[Test]
    public function test_response_getters(): void
    {
        $response = new Response(
            statusCode: 201,
            headers: ['X-Custom' => 'yes'],
            body: ['id' => 1],
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['X-Custom' => 'yes'], $response->getHeaders());
        $this->assertSame(['id' => 1], $response->getBody());
    }

    #[Test]
    public function test_response_with_header_returns_new_instance(): void
    {
        $original = Response::json(['ok' => true]);
        $modified = $original->withHeader('X-Request-Id', '123');

        // Immutable — original unchanged
        $this->assertArrayNotHasKey('X-Request-Id', $original->getHeaders());
        $this->assertSame('123', $modified->getHeaders()['X-Request-Id']);
        $this->assertSame(200, $modified->getStatusCode());
    }

    // ─── Application::handleRequest ─────────────────────────────────

    #[Test]
    public function test_handle_request_returns_404_for_unknown_route(): void
    {
        $app = new Application(
            basePath: $this->basePath,
            modules: [],
        );

        $request = RequestFactory::create(method: 'GET', uri: '/nonexistent');
        $response = $app->handleRequest($request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_handle_request_boots_app_automatically(): void
    {
        $app = new Application(
            basePath: $this->basePath,
            modules: [],
        );

        // handleRequest should boot without explicit boot() call
        $request = RequestFactory::create(method: 'GET', uri: '/anything');
        $response = $app->handleRequest($request);

        // App should have booted and returned a response (404 since no routes)
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_handle_request_routes_to_controller_and_returns_response(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\LifecycleTestModule::class],
        );

        $request = RequestFactory::create(method: 'GET', uri: '/lifecycle/health');
        $response = $app->handleRequest($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['status' => 'ok'], $response->body);
    }

    #[Test]
    public function test_handle_request_with_path_params(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\LifecycleTestModule::class],
        );

        $request = RequestFactory::create(method: 'GET', uri: '/lifecycle/users/42');
        $response = $app->handleRequest($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['id' => '42'], $response->body);
    }

    #[Test]
    public function test_handle_request_controller_exception_returns_error_response(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\LifecycleTestModule::class],
        );

        $request = RequestFactory::create(method: 'GET', uri: '/lifecycle/error');
        $response = $app->handleRequest($request);

        $this->assertSame(500, $response->statusCode);
    }

    #[Test]
    public function test_handle_request_post_with_body(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\LifecycleTestModule::class],
        );

        $request = RequestFactory::create(
            method: 'POST',
            uri: '/lifecycle/echo',
            body: ['message' => 'hello'],
        );
        $response = $app->handleRequest($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['message' => 'hello'], $response->body);
    }
}
