<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\Http\Cors\CorsConfig;
use Lattice\Http\Cors\CorsGuard;
use Lattice\Http\Response;

final class CorsTest extends TestCase
{
    public function test_preflight_returns_204_with_cors_headers(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST', 'PUT'],
            allowedHeaders: ['Content-Type', 'Authorization'],
        );
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://example.com');

        $this->assertSame(204, $response->statusCode);
        $this->assertSame('https://example.com', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST, PUT', $response->headers['Access-Control-Allow-Methods']);
        $this->assertSame('Content-Type, Authorization', $response->headers['Access-Control-Allow-Headers']);
    }

    public function test_wildcard_origin_returns_star(): void
    {
        $config = new CorsConfig(allowedOrigins: ['*']);
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://any-origin.com');

        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
    }

    public function test_explicit_origin_reflects_matching_origin(): void
    {
        $config = new CorsConfig(allowedOrigins: ['https://allowed.com', 'https://also-allowed.com']);
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://also-allowed.com');

        $this->assertSame('https://also-allowed.com', $response->headers['Access-Control-Allow-Origin']);
    }

    public function test_disallowed_origin_gets_no_allow_origin_header(): void
    {
        $config = new CorsConfig(allowedOrigins: ['https://allowed.com']);
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://evil.com');

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers);
    }

    public function test_credentials_support_sets_header(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['https://app.example.com'],
            supportsCredentials: true,
        );
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://app.example.com');

        $this->assertSame('true', $response->headers['Access-Control-Allow-Credentials']);
    }

    public function test_credentials_not_set_when_disabled(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            supportsCredentials: false,
        );
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://example.com');

        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $response->headers);
    }

    public function test_max_age_header_is_set_when_configured(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['*'],
            maxAge: 86400,
        );
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://example.com');

        $this->assertSame('86400', $response->headers['Access-Control-Max-Age']);
    }

    public function test_max_age_header_absent_when_zero(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['*'],
            maxAge: 0,
        );
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://example.com');

        $this->assertArrayNotHasKey('Access-Control-Max-Age', $response->headers);
    }

    public function test_apply_headers_adds_cors_to_response(): void
    {
        $config = new CorsConfig(
            allowedOrigins: ['https://spa.example.com'],
            exposedHeaders: ['X-Request-Id', 'X-Rate-Limit'],
        );
        $guard = new CorsGuard($config);

        $original = Response::json(['ok' => true]);
        $corsResponse = $guard->applyHeaders($original, 'https://spa.example.com');

        $this->assertSame('https://spa.example.com', $corsResponse->headers['Access-Control-Allow-Origin']);
        $this->assertSame('X-Request-Id, X-Rate-Limit', $corsResponse->headers['Access-Control-Expose-Headers']);
        $this->assertSame(200, $corsResponse->statusCode);
    }

    public function test_apply_headers_preserves_original_body(): void
    {
        $config = new CorsConfig(allowedOrigins: ['*']);
        $guard = new CorsGuard($config);

        $body = ['data' => [1, 2, 3]];
        $original = Response::json($body);
        $corsResponse = $guard->applyHeaders($original, 'https://example.com');

        $this->assertSame($body, $corsResponse->body);
    }

    public function test_config_from_array(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://custom.com'],
            'allowedMethods' => ['GET'],
            'allowedHeaders' => ['Accept'],
            'exposedHeaders' => ['X-Custom'],
            'maxAge' => 3600,
            'supportsCredentials' => true,
        ]);
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://custom.com');

        $this->assertSame('https://custom.com', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET', $response->headers['Access-Control-Allow-Methods']);
        $this->assertSame('Accept', $response->headers['Access-Control-Allow-Headers']);
        $this->assertSame('3600', $response->headers['Access-Control-Max-Age']);
        $this->assertSame('true', $response->headers['Access-Control-Allow-Credentials']);
    }

    public function test_default_config_allows_all_origins(): void
    {
        $config = CorsConfig::default();
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://anything.com');

        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
    }
}
