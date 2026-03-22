<?php

declare(strict_types=1);

namespace Lattice\Http;

final readonly class Request
{
    /**
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array<string, string> $headers Request headers
     * @param array<string, string> $query Query parameters
     * @param mixed $body Parsed request body
     * @param array<string, string> $pathParams Path parameters
     */
    public function __construct(
        public string $method,
        public string $uri,
        public array $headers = [],
        public array $query = [],
        public mixed $body = null,
        public array $pathParams = [],
    ) {}

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Get a value from the JSON body, or the entire body if no key is given.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        if (is_array($this->body) && array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        return $default;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    public function bearerToken(): ?string
    {
        $header = $this->getHeader('Authorization');
        if ($header === null) {
            return null;
        }

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public function getQuery(string $key): ?string
    {
        return $this->query[$key] ?? null;
    }

    public function getHeader(string $name): ?string
    {
        // Case-insensitive header lookup
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function getParam(string $name): ?string
    {
        return $this->pathParams[$name] ?? null;
    }

    /**
     * Return a new Request with the given path parameters.
     *
     * @param array<string, string> $pathParams
     */
    public function withPathParams(array $pathParams): self
    {
        return new self(
            method: $this->method,
            uri: $this->uri,
            headers: $this->headers,
            query: $this->query,
            body: $this->body,
            pathParams: $pathParams,
        );
    }
}
