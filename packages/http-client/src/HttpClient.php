<?php

declare(strict_types=1);

namespace Lattice\HttpClient;

class HttpClient
{
    /** @var array<string, string> */
    private array $headers = [];

    private int $timeout = 30;

    /**
     * Send a GET request.
     *
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('GET', $url, null, $options);
    }

    /**
     * Send a POST request.
     *
     * @param array<string, mixed> $options
     */
    public function post(string $url, mixed $body = null, array $options = []): HttpClientResponse
    {
        return $this->request('POST', $url, $body, $options);
    }

    /**
     * Send a PUT request.
     *
     * @param array<string, mixed> $options
     */
    public function put(string $url, mixed $body = null, array $options = []): HttpClientResponse
    {
        return $this->request('PUT', $url, $body, $options);
    }

    /**
     * Send a PATCH request.
     *
     * @param array<string, mixed> $options
     */
    public function patch(string $url, mixed $body = null, array $options = []): HttpClientResponse
    {
        return $this->request('PATCH', $url, $body, $options);
    }

    /**
     * Send a DELETE request.
     *
     * @param array<string, mixed> $options
     */
    public function delete(string $url, array $options = []): HttpClientResponse
    {
        return $this->request('DELETE', $url, null, $options);
    }

    /**
     * Return a new instance with the given headers merged in.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);
        return $clone;
    }

    /**
     * Return a new instance with a Bearer token set.
     */
    public function withAuth(string $token): static
    {
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    /**
     * Return a new instance with the given timeout.
     */
    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    /**
     * Execute an HTTP request using PHP streams.
     *
     * @param array<string, mixed> $options
     */
    protected function request(string $method, string $url, mixed $body = null, array $options = []): HttpClientResponse
    {
        $headers = array_merge($this->headers, $options['headers'] ?? []);

        $content = null;
        if ($body !== null) {
            if (is_array($body) || is_object($body)) {
                $content = json_encode($body, JSON_THROW_ON_ERROR);
                $headers['Content-Type'] ??= 'application/json';
            } else {
                $content = (string) $body;
            }
        }

        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = "{$key}: {$value}";
        }

        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $content,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($contextOptions);

        $responseBody = @file_get_contents($url, false, $context);

        if ($responseBody === false) {
            throw new HttpClientException("HTTP request to {$url} failed.");
        }

        /** @var list<string> $http_response_header */
        $statusCode = $this->parseStatusCode($http_response_header ?? []);
        $responseHeaders = $this->parseHeaders($http_response_header ?? []);

        return new HttpClientResponse($statusCode, $responseHeaders, $responseBody);
    }

    /**
     * @param list<string> $rawHeaders
     */
    private function parseStatusCode(array $rawHeaders): int
    {
        if (empty($rawHeaders)) {
            return 0;
        }

        // First line: "HTTP/1.1 200 OK"
        if (preg_match('#HTTP/\S+\s+(\d{3})#', $rawHeaders[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param list<string> $rawHeaders
     * @return array<string, string>
     */
    private function parseHeaders(array $rawHeaders): array
    {
        $headers = [];

        foreach ($rawHeaders as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
