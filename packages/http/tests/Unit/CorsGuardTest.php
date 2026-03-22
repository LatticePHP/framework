<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Cors\CorsConfig;
use Lattice\Http\Cors\CorsGuard;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Request;
use Lattice\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorsGuardTest extends TestCase
{
    #[Test]
    public function test_can_activate_returns_true_for_non_preflight(): void
    {
        $guard = new CorsGuard(CorsConfig::default());
        $context = $this->createContext('GET', 'https://example.com');

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function test_can_activate_returns_true_for_allowed_origin(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://example.com'],
        ]);
        $guard = new CorsGuard($config);
        $context = $this->createContext('GET', 'https://example.com');

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function test_can_activate_returns_false_for_disallowed_origin(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://allowed.com'],
        ]);
        $guard = new CorsGuard($config);
        $context = $this->createContext('GET', 'https://evil.com');

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function test_wildcard_origin_allows_all(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['*'],
        ]);
        $guard = new CorsGuard($config);
        $context = $this->createContext('GET', 'https://anything.com');

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function test_apply_headers_adds_cors_headers(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://example.com'],
            'allowedMethods' => ['GET', 'POST'],
            'allowedHeaders' => ['Content-Type', 'Authorization'],
            'exposedHeaders' => ['X-Request-Id'],
            'maxAge' => 3600,
            'supportsCredentials' => true,
        ]);
        $guard = new CorsGuard($config);

        $response = new Response(200, [], 'ok');
        $result = $guard->applyHeaders($response, 'https://example.com');

        $this->assertEquals('https://example.com', $result->headers['Access-Control-Allow-Origin']);
        $this->assertEquals('true', $result->headers['Access-Control-Allow-Credentials']);
        $this->assertEquals('X-Request-Id', $result->headers['Access-Control-Expose-Headers']);
    }

    #[Test]
    public function test_preflight_response(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://example.com'],
            'allowedMethods' => ['GET', 'POST', 'PUT'],
            'allowedHeaders' => ['Content-Type', 'Authorization'],
            'maxAge' => 7200,
        ]);
        $guard = new CorsGuard($config);

        $response = $guard->handlePreflight('https://example.com');

        $this->assertEquals(204, $response->statusCode);
        $this->assertEquals('https://example.com', $response->headers['Access-Control-Allow-Origin']);
        $this->assertEquals('GET, POST, PUT', $response->headers['Access-Control-Allow-Methods']);
        $this->assertEquals('Content-Type, Authorization', $response->headers['Access-Control-Allow-Headers']);
        $this->assertEquals('7200', $response->headers['Access-Control-Max-Age']);
    }

    #[Test]
    public function test_default_config(): void
    {
        $config = CorsConfig::default();

        $this->assertEquals(['*'], $config->allowedOrigins);
        $this->assertContains('GET', $config->allowedMethods);
        $this->assertContains('POST', $config->allowedMethods);
        $this->assertContains('Content-Type', $config->allowedHeaders);
        $this->assertFalse($config->supportsCredentials);
    }

    #[Test]
    public function test_from_array_creates_config(): void
    {
        $config = CorsConfig::fromArray([
            'allowedOrigins' => ['https://a.com'],
            'maxAge' => 600,
        ]);

        $this->assertEquals(['https://a.com'], $config->allowedOrigins);
        $this->assertEquals(600, $config->maxAge);
    }

    private function createContext(string $method, string $origin): ExecutionContextInterface
    {
        $request = new Request(
            method: $method,
            uri: '/test',
            headers: ['Origin' => $origin],
        );

        return new HttpExecutionContext(
            request: $request,
            module: 'TestModule',
            controllerClass: 'TestController',
            methodName: 'index',
        );
    }
}
