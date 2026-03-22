<?php

declare(strict_types=1);

namespace Lattice\Testing\Http;

/**
 * In-memory HTTP test client for integration testing.
 *
 * The handler callable receives (string $method, string $uri, array $headers, mixed $body)
 * and must return a TestResponse.
 */
final class HttpTestClient
{
    /** @var array<string, string> */
    private array $defaultHeaders = [];

    /**
     * @param callable(string, string, array<string, string>, mixed): TestResponse $handler
     */
    public function __construct(
        private readonly mixed $handler,
    ) {}

    /**
     * Add an Authorization: Bearer header to all subsequent requests.
     */
    public function withAuth(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Add a default header to all subsequent requests.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->send('GET', $uri, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function post(string $uri, mixed $body = null, array $headers = []): TestResponse
    {
        return $this->send('POST', $uri, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function put(string $uri, mixed $body = null, array $headers = []): TestResponse
    {
        return $this->send('PUT', $uri, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function patch(string $uri, mixed $body = null, array $headers = []): TestResponse
    {
        return $this->send('PATCH', $uri, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function delete(string $uri, array $headers = []): TestResponse
    {
        return $this->send('DELETE', $uri, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, string $uri, mixed $body, array $headers): TestResponse
    {
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        return ($this->handler)($method, $uri, $mergedHeaders, $body);
    }
}
