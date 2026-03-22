<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests;

use Lattice\Testing\Http\HttpTestClient;
use Lattice\Testing\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpTestClientTest extends TestCase
{
    #[Test]
    public function it_sends_get_requests(): void
    {
        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body): TestResponse {
            return new TestResponse(200, ['Content-Type' => 'application/json'], ['message' => 'ok']);
        });

        $response = $client->get('/api/users');

        $this->assertInstanceOf(TestResponse::class, $response);
        $response->assertStatus(200);
    }

    #[Test]
    public function it_sends_post_requests_with_body(): void
    {
        $receivedBody = null;

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedBody): TestResponse {
            $receivedBody = $body;
            return new TestResponse(201, [], ['id' => 1]);
        });

        $client->post('/api/users', ['name' => 'John']);

        $this->assertSame(['name' => 'John'], $receivedBody);
    }

    #[Test]
    public function it_sends_put_requests(): void
    {
        $receivedMethod = null;

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedMethod): TestResponse {
            $receivedMethod = $method;
            return new TestResponse(200, [], null);
        });

        $client->put('/api/users/1', ['name' => 'Jane']);

        $this->assertSame('PUT', $receivedMethod);
    }

    #[Test]
    public function it_sends_patch_requests(): void
    {
        $receivedMethod = null;

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedMethod): TestResponse {
            $receivedMethod = $method;
            return new TestResponse(200, [], null);
        });

        $client->patch('/api/users/1', ['name' => 'Jane']);

        $this->assertSame('PATCH', $receivedMethod);
    }

    #[Test]
    public function it_sends_delete_requests(): void
    {
        $receivedMethod = null;

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedMethod): TestResponse {
            $receivedMethod = $method;
            return new TestResponse(204, [], null);
        });

        $client->delete('/api/users/1');

        $this->assertSame('DELETE', $receivedMethod);
    }

    #[Test]
    public function it_passes_custom_headers(): void
    {
        $receivedHeaders = [];

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedHeaders): TestResponse {
            $receivedHeaders = $headers;
            return new TestResponse(200, [], null);
        });

        $client->get('/api/users', ['X-Custom' => 'value']);

        $this->assertSame('value', $receivedHeaders['X-Custom']);
    }

    #[Test]
    public function it_adds_auth_token(): void
    {
        $receivedHeaders = [];

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedHeaders): TestResponse {
            $receivedHeaders = $headers;
            return new TestResponse(200, [], null);
        });

        $response = $client->withAuth('my-token')->get('/api/protected');

        $this->assertSame('Bearer my-token', $receivedHeaders['Authorization']);
    }

    #[Test]
    public function it_adds_custom_default_headers(): void
    {
        $receivedHeaders = [];

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedHeaders): TestResponse {
            $receivedHeaders = $headers;
            return new TestResponse(200, [], null);
        });

        $client->withHeader('X-Tenant', 'acme')
            ->withHeader('Accept-Language', 'en')
            ->get('/api/data');

        $this->assertSame('acme', $receivedHeaders['X-Tenant']);
        $this->assertSame('en', $receivedHeaders['Accept-Language']);
    }

    #[Test]
    public function it_merges_default_and_request_headers(): void
    {
        $receivedHeaders = [];

        $client = new HttpTestClient(function (string $method, string $uri, array $headers, mixed $body) use (&$receivedHeaders): TestResponse {
            $receivedHeaders = $headers;
            return new TestResponse(200, [], null);
        });

        $client->withHeader('X-Default', 'default-value')
            ->get('/api/data', ['X-Request' => 'request-value']);

        $this->assertSame('default-value', $receivedHeaders['X-Default']);
        $this->assertSame('request-value', $receivedHeaders['X-Request']);
    }
}
