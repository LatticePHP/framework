<?php

declare(strict_types=1);

namespace Lattice\HttpClient\Testing;

use Lattice\HttpClient\HttpClient;
use Lattice\HttpClient\HttpClientResponse;

final class FakeHttpClient extends HttpClient
{
    /** @var array<string, HttpClientResponse> */
    private array $stubs = [];

    /** @var list<string> */
    private array $sentUrls = [];

    /**
     * Register a stub response for a given URL.
     */
    public function stub(string $url, HttpClientResponse $response): void
    {
        $this->stubs[$url] = $response;
    }

    /**
     * Assert that a request was sent to the given URL.
     *
     * @throws \RuntimeException
     */
    public function assertSent(string $url): void
    {
        if (!in_array($url, $this->sentUrls, true)) {
            throw new \RuntimeException("Expected a request to [{$url}], but none was sent.");
        }
    }

    /**
     * Assert that a request was NOT sent to the given URL.
     *
     * @throws \RuntimeException
     */
    public function assertNotSent(string $url): void
    {
        if (in_array($url, $this->sentUrls, true)) {
            throw new \RuntimeException("Unexpected request to [{$url}] was sent.");
        }
    }

    protected function request(string $method, string $url, mixed $body = null, array $options = []): HttpClientResponse
    {
        $this->sentUrls[] = $url;

        if (isset($this->stubs[$url])) {
            return $this->stubs[$url];
        }

        // Return a 404 for unstubbed URLs
        return new HttpClientResponse(404, [], 'Not Found');
    }
}
