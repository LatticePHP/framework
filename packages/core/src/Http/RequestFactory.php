<?php

declare(strict_types=1);

namespace Lattice\Core\Http;

use Lattice\Http\Request;

final class RequestFactory
{
    /**
     * Construct a Lattice Request from PHP superglobals.
     */
    public static function fromGlobals(): Request
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        parse_str($queryString, $query);

        // Parse headers from $_SERVER['HTTP_*']
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        // Content-Type and Content-Length aren't prefixed with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        // Parse body
        $rawBody = file_get_contents('php://input');
        $body = [];
        $contentType = $headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json') && $rawBody !== '' && $rawBody !== false) {
            $body = json_decode($rawBody, true) ?? [];
        }

        return new Request(
            method: strtoupper($method),
            uri: $uri,
            headers: $headers,
            query: $query,
            body: $body,
            pathParams: [],
        );
    }

    /**
     * Construct a Lattice Request from explicit parameters.
     * Useful for testing and non-FPM runtimes (RoadRunner, OpenSwoole).
     *
     * @param string $method HTTP method
     * @param string $uri Request URI path
     * @param array<string, string> $headers Request headers
     * @param array<string, string> $query Query parameters
     * @param mixed $body Parsed request body
     */
    public static function create(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        array $query = [],
        mixed $body = null,
    ): Request {
        return new Request(
            method: strtoupper($method),
            uri: $uri,
            headers: $headers,
            query: $query,
            body: $body,
            pathParams: [],
        );
    }
}
