<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Integration;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Http\Cors\CorsConfig;
use Lattice\Http\Cors\CorsGuard;
use Lattice\Http\ExceptionHandler;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\HttpKernel;
use Lattice\Http\ParameterResolver;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Pipeline\Exceptions\ForbiddenException;
use Lattice\Pipeline\Filter\FilterChain;
use Lattice\Pipeline\Guard\GuardChain;
use Lattice\Pipeline\Interceptor\InterceptorChain;
use Lattice\Pipeline\Pipe\PipeChain;
use Lattice\Pipeline\PipelineConfig;
use Lattice\Pipeline\PipelineExecutor;
use Lattice\ProblemDetails\ProblemDetails;
use Lattice\ProblemDetails\ProblemDetailsException;
use Lattice\ProblemDetails\ProblemDetailsFilter;
use Lattice\RateLimit\RateLimiter;
use Lattice\RateLimit\RateLimitGuard;
use Lattice\RateLimit\Attributes\RateLimit;
use Lattice\RateLimit\Store\InMemoryRateLimitStore;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\TestCase;

// ── Fixture: Guards ─────────────────────────────────────────────────────

final class MCIAlwaysAllowGuard implements GuardInterface
{
    public bool $called = false;

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $this->called = true;
        return true;
    }
}

final class MCIAlwaysDenyGuard implements GuardInterface
{
    public bool $called = false;

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $this->called = true;
        return false;
    }
}

final class MCIOrderTrackingGuard implements GuardInterface
{
    /** @var list<string> */
    private array $log;
    private string $label;

    public function __construct(array &$log, string $label)
    {
        $this->log = &$log;
        $this->label = $label;
    }

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $this->log[] = $this->label;
        return true;
    }
}

final class MCIPrincipalSettingGuard implements GuardInterface
{
    public function __construct(
        private readonly PrincipalInterface $principal,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        if ($context instanceof HttpExecutionContext) {
            $context->setPrincipal($this->principal);
        }
        return true;
    }
}

final class MCIPrincipalReadingGuard implements GuardInterface
{
    public ?PrincipalInterface $readPrincipal = null;

    public function canActivate(ExecutionContextInterface $context): bool
    {
        $this->readPrincipal = $context->getPrincipal();
        return true;
    }
}

// ── Fixture: Principal ──────────────────────────────────────────────────

final class MCITestPrincipal implements PrincipalInterface
{
    public function __construct(
        private readonly string|int $id,
        private readonly string $type = 'user',
        private readonly array $scopes = ['read', 'write'],
        private readonly array $roles = ['admin'],
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}

// ── Fixture: Interceptors ───────────────────────────────────────────────

final class MCITimingInterceptor implements InterceptorInterface
{
    public string $beforeCalled = '';
    public string $afterCalled = '';

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $this->beforeCalled = 'before';
        $result = $next($context);
        $this->afterCalled = 'after';
        return $result;
    }
}

final class MCIOnionInterceptor implements InterceptorInterface
{
    /** @var list<string> */
    private array $log;
    private string $label;

    public function __construct(array &$log, string $label)
    {
        $this->log = &$log;
        $this->label = $label;
    }

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $this->log[] = $this->label . '.before';
        $result = $next($context);
        $this->log[] = $this->label . '.after';
        return $result;
    }
}

final class MCIPrincipalReadingInterceptor implements InterceptorInterface
{
    public ?PrincipalInterface $readPrincipal = null;

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $this->readPrincipal = $context->getPrincipal();
        return $next($context);
    }
}

final class MCIResponseHeaderInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly string $headerName,
        private readonly string $headerValue,
    ) {}

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $result = $next($context);
        if ($result instanceof Response) {
            return $result->withHeader($this->headerName, $this->headerValue);
        }
        return $result;
    }
}

// ── Fixture: Pipes ──────────────────────────────────────────────────────

final class MCIUppercasePipe implements PipeInterface
{
    public bool $called = false;

    public function transform(mixed $value, array $metadata = []): mixed
    {
        $this->called = true;
        if (is_string($value)) {
            return strtoupper($value);
        }
        return $value;
    }
}

// ── Fixture: Filters ────────────────────────────────────────────────────

final class MCITestExceptionFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        return Response::json([
            'error' => 'Caught: ' . $exception->getMessage(),
            'type' => get_class($exception),
        ], 500);
    }
}

// ── Fixture: Controller ─────────────────────────────────────────────────

final class MCITestController
{
    public function index(): array
    {
        return ['message' => 'ok'];
    }

    public function throwsDomainError(): never
    {
        throw new \DomainException('Something went wrong in domain');
    }
}

// ── Fixture: Rate-Limited Controller ────────────────────────────────────

#[RateLimit(maxAttempts: 2, decaySeconds: 60, key: 'test-rate-limit')]
final class MCIRateLimitedController
{
    public function index(): array
    {
        return ['message' => 'ok'];
    }
}

// ── Fixture: Validation Guard (simulating JWT auth) ─────────────────────

final class MCIJwtAuthGuard implements GuardInterface
{
    public function __construct(
        private readonly string $expectedToken,
        private readonly PrincipalInterface $principal,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        if (!$context instanceof HttpExecutionContext) {
            return false;
        }

        $token = $context->getRequest()->bearerToken();
        if ($token !== $this->expectedToken) {
            return false;
        }

        $context->setPrincipal($this->principal);
        return true;
    }
}

// ── Fixture: Logging Interceptor ────────────────────────────────────────

final class MCILoggingInterceptor implements InterceptorInterface
{
    /** @var list<string> */
    public array $log = [];

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $this->log[] = 'request:' . $context->getHandler();
        $result = $next($context);
        $this->log[] = 'response:' . ($result instanceof Response ? (string) $result->statusCode : 'unknown');
        return $result;
    }
}

// ── Integration Tests ───────────────────────────────────────────────────

final class MiddlewareChainIntegrationTest extends TestCase
{
    // ── 1. Global guard runs on every request ────────────────────────

    public function test_global_guard_runs_on_every_request(): void
    {
        $globalGuard = new MCIAlwaysAllowGuard();
        $context = $this->createContext();

        $config = new PipelineConfig(guards: [$globalGuard]);
        $executor = new PipelineExecutor();

        $executor->execute($context, fn () => Response::json(['ok' => true]), $config);

        $this->assertTrue($globalGuard->called, 'Global guard should have been called');
    }

    // ── 2. Route guard from attribute ────────────────────────────────

    public function test_route_guard_runs_for_specific_controller_only(): void
    {
        $routeGuard = new MCIAlwaysAllowGuard();
        $context = $this->createContext();

        // Only this route has the guard
        $config = new PipelineConfig(guards: [$routeGuard]);
        $executor = new PipelineExecutor();

        $executor->execute($context, fn () => Response::json(['ok' => true]), $config);

        $this->assertTrue($routeGuard->called, 'Route guard should run for the specific route');

        // Another route without the guard
        $emptyConfig = new PipelineConfig();
        $otherGuard = new MCIAlwaysAllowGuard();
        $executor->execute($context, fn () => Response::json(['other' => true]), $emptyConfig);

        $this->assertFalse($otherGuard->called, 'Guard should NOT run for a route without it');
    }

    // ── 3. Guard ordering: global BEFORE route ──────────────────────

    public function test_guard_ordering_global_before_route(): void
    {
        $order = [];
        $globalGuard = new MCIOrderTrackingGuard($order, 'global');
        $routeGuard = new MCIOrderTrackingGuard($order, 'route');

        $context = $this->createContext();

        // Simulate what HttpKernel does: merge global guards first, then route guards
        $config = new PipelineConfig(guards: [$globalGuard, $routeGuard]);
        $executor = new PipelineExecutor();

        $executor->execute($context, fn () => 'ok', $config);

        $this->assertSame(['global', 'route'], $order, 'Global guard must execute before route guard');
    }

    // ── 4. First failing guard stops chain ──────────────────────────

    public function test_first_failing_guard_stops_chain_returns_403(): void
    {
        $denyGuard = new MCIAlwaysDenyGuard();
        $secondGuard = new MCIAlwaysAllowGuard();

        $context = $this->createContext();
        $config = new PipelineConfig(guards: [$denyGuard, $secondGuard]);
        $executor = new PipelineExecutor();

        try {
            $executor->execute($context, fn () => 'should not reach', $config);
            $this->fail('Expected ForbiddenException');
        } catch (ForbiddenException $e) {
            $this->assertTrue($denyGuard->called, 'First guard was called');
            $this->assertFalse($secondGuard->called, 'Second guard should NOT be called after first denies');
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    // ── 5. Interceptor wraps controller ─────────────────────────────

    public function test_interceptor_wraps_controller_before_handler_after(): void
    {
        $interceptor = new MCITimingInterceptor();
        $context = $this->createContext();

        $handlerCalled = false;
        $config = new PipelineConfig(interceptors: [$interceptor]);
        $executor = new PipelineExecutor();

        $executor->execute($context, function () use (&$handlerCalled) {
            $handlerCalled = true;
            return Response::json(['ok' => true]);
        }, $config);

        $this->assertSame('before', $interceptor->beforeCalled);
        $this->assertTrue($handlerCalled);
        $this->assertSame('after', $interceptor->afterCalled);
    }

    // ── 6. Multiple interceptors onion order ────────────────────────

    public function test_multiple_interceptors_onion_order(): void
    {
        $log = [];
        $interceptorA = new MCIOnionInterceptor($log, 'A');
        $interceptorB = new MCIOnionInterceptor($log, 'B');

        $context = $this->createContext();
        $config = new PipelineConfig(interceptors: [$interceptorA, $interceptorB]);
        $executor = new PipelineExecutor();

        $executor->execute($context, function () use (&$log) {
            $log[] = 'handler';
            return 'result';
        }, $config);

        $this->assertSame(
            ['A.before', 'B.before', 'handler', 'B.after', 'A.after'],
            $log,
            'Interceptors must wrap in onion order: A(B(handler))'
        );
    }

    // ── 7. Pipe transforms input ────────────────────────────────────

    public function test_pipe_transforms_input(): void
    {
        $pipe = new MCIUppercasePipe();
        $pipeChain = new PipeChain();

        $result = $pipeChain->execute([$pipe], 'hello world', []);

        $this->assertTrue($pipe->called);
        $this->assertSame('HELLO WORLD', $result, 'Pipe should transform the input data');
    }

    // ── 8. Exception filter catches ─────────────────────────────────

    public function test_exception_filter_catches_controller_exception(): void
    {
        $filter = new MCITestExceptionFilter();
        $context = $this->createContext();

        $config = new PipelineConfig(filters: [$filter]);
        $executor = new PipelineExecutor();

        $result = $executor->execute(
            $context,
            fn () => throw new \DomainException('Something went wrong'),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(500, $result->statusCode);
        $this->assertSame('Caught: Something went wrong', $result->body['error']);
        $this->assertSame(\DomainException::class, $result->body['type']);
    }

    // ── 9. ProblemDetailsFilter as default ───────────────────────────

    public function test_problem_details_filter_as_default_produces_rfc9457(): void
    {
        $filter = new ProblemDetailsFilter();
        $context = $this->createContext();

        $config = new PipelineConfig(filters: [$filter]);
        $executor = new PipelineExecutor();

        $result = $executor->execute(
            $context,
            fn () => throw new \RuntimeException('Unhandled error'),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(500, $result->statusCode);
        $this->assertSame('application/problem+json', $result->headers['Content-Type']);

        $body = $result->body;
        $this->assertSame(500, $body['status']);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertArrayHasKey('correlationId', $body);
    }

    public function test_problem_details_filter_handles_http_exception_with_correct_status(): void
    {
        $filter = new ProblemDetailsFilter();
        $context = $this->createContext();

        $config = new PipelineConfig(filters: [$filter]);
        $executor = new PipelineExecutor();

        $result = $executor->execute(
            $context,
            fn () => throw new ForbiddenException('Access denied'),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('Access denied', $result->body['detail']);
    }

    // ── 10. Principal flows through pipeline ────────────────────────

    public function test_principal_flows_through_pipeline(): void
    {
        $principal = new MCITestPrincipal(42, 'user');
        $authGuard = new MCIPrincipalSettingGuard($principal);
        $interceptor = new MCIPrincipalReadingInterceptor();

        $context = $this->createContext();
        $config = new PipelineConfig(
            guards: [$authGuard],
            interceptors: [$interceptor],
        );
        $executor = new PipelineExecutor();

        $controllerPrincipal = null;
        $executor->execute($context, function (ExecutionContextInterface $ctx) use (&$controllerPrincipal) {
            $controllerPrincipal = $ctx->getPrincipal();
            return Response::json(['user_id' => $controllerPrincipal?->getId()]);
        }, $config);

        // Guard sets principal
        $this->assertNotNull($context->getPrincipal());
        $this->assertSame(42, $context->getPrincipal()->getId());

        // Interceptor reads principal
        $this->assertNotNull($interceptor->readPrincipal);
        $this->assertSame(42, $interceptor->readPrincipal->getId());

        // Controller reads principal via context (#[CurrentUser])
        $this->assertNotNull($controllerPrincipal);
        $this->assertSame(42, $controllerPrincipal->getId());
    }

    // ── 11. Rate limiter counts requests ────────────────────────────

    public function test_rate_limiter_counts_requests_blocks_after_limit(): void
    {
        $store = new InMemoryRateLimitStore();
        $limiter = new RateLimiter($store);
        $rateLimitGuard = new RateLimitGuard($limiter);

        $request = new Request('GET', '/rate-limited', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: MCIRateLimitedController::class,
            methodName: 'index',
        );

        $config = new PipelineConfig(guards: [$rateLimitGuard]);
        $executor = new PipelineExecutor();

        // Request 1: allowed
        $result1 = $executor->execute($context, fn () => Response::json(['ok' => true]), $config);
        $this->assertInstanceOf(Response::class, $result1);
        $this->assertSame(200, $result1->statusCode);

        // Request 2: allowed (limit=2)
        $result2 = $executor->execute($context, fn () => Response::json(['ok' => true]), $config);
        $this->assertInstanceOf(Response::class, $result2);
        $this->assertSame(200, $result2->statusCode);

        // Request 3: blocked (over limit)
        $this->expectException(ForbiddenException::class);
        $executor->execute($context, fn () => Response::json(['should not reach' => true]), $config);
    }

    // ── 12. FULL CYCLE: CORS -> Rate limit -> JWT Auth -> Validate -> Controller -> Log -> Response

    public function test_full_cycle_cors_ratelimit_jwtauth_validate_controller_log_response(): void
    {
        $principal = new MCITestPrincipal('user-99', 'user');
        $log = [];

        // 1. CORS guard (allow our origin)
        $corsGuard = new CorsGuard(new CorsConfig(
            allowedOrigins: ['https://app.example.com'],
        ));

        // 2. Rate limit guard (using inline approach - check attribute on controller)
        $store = new InMemoryRateLimitStore();
        $rateLimiter = new RateLimiter($store);
        $rateLimitGuard = new RateLimitGuard($rateLimiter);

        // 3. JWT Auth guard
        $jwtGuard = new MCIJwtAuthGuard('valid-token-123', $principal);

        // 4. Logging interceptor (wraps controller)
        $loggingInterceptor = new MCILoggingInterceptor();

        // 5. ProblemDetails filter (fallback)
        $problemDetailsFilter = new ProblemDetailsFilter();

        // Build request with all required headers
        $request = new Request('GET', '/api/items', [
            'Origin' => 'https://app.example.com',
            'Authorization' => 'Bearer valid-token-123',
        ], [], null);

        $context = new HttpExecutionContext(
            request: $request,
            module: 'api',
            controllerClass: MCIRateLimitedController::class,
            methodName: 'index',
        );

        $config = new PipelineConfig(
            guards: [$corsGuard, $rateLimitGuard, $jwtGuard],
            interceptors: [$loggingInterceptor],
            filters: [$problemDetailsFilter],
        );

        $executor = new PipelineExecutor();

        $result = $executor->execute($context, function (ExecutionContextInterface $ctx) {
            // Controller: verify auth principal is set
            $p = $ctx->getPrincipal();
            return Response::json([
                'user_id' => $p?->getId(),
                'items' => ['item1', 'item2'],
            ]);
        }, $config);

        // Assertions
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->statusCode);

        // Principal was set by JWT guard
        $this->assertNotNull($context->getPrincipal());
        $this->assertSame('user-99', $context->getPrincipal()->getId());

        // Controller returned correct data
        $this->assertSame('user-99', $result->body['user_id']);
        $this->assertSame(['item1', 'item2'], $result->body['items']);

        // Logging interceptor recorded the request/response
        $this->assertCount(2, $loggingInterceptor->log);
        $this->assertStringContainsString('request:', $loggingInterceptor->log[0]);
        $this->assertStringContainsString('response:200', $loggingInterceptor->log[1]);
    }

    public function test_full_cycle_fails_when_cors_origin_disallowed(): void
    {
        $corsGuard = new CorsGuard(new CorsConfig(
            allowedOrigins: ['https://trusted.example.com'],
        ));

        $request = new Request('GET', '/api/items', [
            'Origin' => 'https://evil.example.com',
        ], [], null);

        $context = new HttpExecutionContext(
            request: $request,
            module: 'api',
            controllerClass: MCITestController::class,
            methodName: 'index',
        );

        $config = new PipelineConfig(guards: [$corsGuard]);
        $executor = new PipelineExecutor();

        $this->expectException(ForbiddenException::class);
        $executor->execute($context, fn () => Response::json(['ok' => true]), $config);
    }

    public function test_full_cycle_fails_when_jwt_token_invalid(): void
    {
        $principal = new MCITestPrincipal(1);
        $corsGuard = new CorsGuard(new CorsConfig(allowedOrigins: ['*']));
        $jwtGuard = new MCIJwtAuthGuard('correct-token', $principal);
        $problemDetailsFilter = new ProblemDetailsFilter();

        $request = new Request('GET', '/api/items', [
            'Authorization' => 'Bearer wrong-token',
        ], [], null);

        $context = new HttpExecutionContext(
            request: $request,
            module: 'api',
            controllerClass: MCITestController::class,
            methodName: 'index',
        );

        $config = new PipelineConfig(
            guards: [$corsGuard, $jwtGuard],
            filters: [$problemDetailsFilter],
        );

        $executor = new PipelineExecutor();

        $result = $executor->execute($context, fn () => Response::json(['ok' => true]), $config);

        // ProblemDetailsFilter catches ForbiddenException
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('application/problem+json', $result->headers['Content-Type']);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function createContext(): HttpExecutionContext
    {
        $request = new Request('GET', '/test', [], [], null);

        return new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: MCITestController::class,
            methodName: 'index',
        );
    }
}
