<?php

declare(strict_types=1);

namespace Lattice\HttpClient;

final readonly class HttpClientResponse
{
    /**
     * @param int $statusCode HTTP status code
     * @param array<string, string> $responseHeaders
     * @param string $responseBody Raw response body
     */
    public function __construct(
        private int $statusCode,
        private array $responseHeaders,
        private string $responseBody,
    ) {}

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->responseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->responseBody, true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->responseHeaders;
    }

    /**
     * Whether the response status is 2xx.
     */
    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Alias for ok().
     */
    public function successful(): bool
    {
        return $this->ok();
    }

    /**
     * Whether the response status is 4xx or 5xx.
     */
    public function failed(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 600;
    }

    /**
     * Throw an exception if the response is a failure.
     *
     * @return $this
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new HttpClientException(
                "HTTP request failed with status {$this->statusCode}: {$this->responseBody}",
                $this->statusCode,
            );
        }

        return $this;
    }
}
