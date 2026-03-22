<?php

declare(strict_types=1);

namespace Lattice\Http;

final readonly class Response
{
    /**
     * @param int $statusCode HTTP status code
     * @param array<string, string> $headers Response headers
     * @param mixed $body Response body
     */
    public function __construct(
        public int $statusCode = 200,
        public array $headers = [],
        public mixed $body = null,
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Return a new Response with the given header set.
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self(
            statusCode: $this->statusCode,
            headers: $headers,
            body: $this->body,
        );
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
            body: $data,
        );
    }

    public static function noContent(): self
    {
        return new self(statusCode: 204);
    }

    public static function error(string $message, int $status): self
    {
        return new self(
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
            body: ['error' => $message],
        );
    }
}
