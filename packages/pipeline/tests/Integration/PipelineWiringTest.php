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
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\TestCase;

// --- Test Fixtures (inline for integration test clarity) ---

final class HeaderCheckGuard implements GuardInterface
{
    public function __construct(
        private readonly string $headerName,
        private readonly string $expectedValue,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        if (!$context instanceof HttpExecutionContext) {
            return false;
        }

        return $context->getRequest()->getHeader($this->headerName) === $this->expectedValue;
    }
}

final class ResponseHeaderInterceptor implements InterceptorInterface
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

final class UppercasePipe implements PipeInterface
{
    public function transform(mixed $value, array $metadata = []): mixed
    {
        if (is_string($value)) {
            return strtoupper($value);
        }

        return $value;
    }
}

final class TestExceptionFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        if ($exception instanceof ForbiddenException) {
            return Response::error('Access Denied by Filter', 403);
        }

        throw $exception;
    }
}

final class AlwaysAllowGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        return true;
    }
}

final class AlwaysDenyGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        return false;
    }
}

final class PrincipalSettingGuard implements GuardInterface
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

final class TestPrincipal implements PrincipalInterface
{
    public function __construct(
        private readonly string|int $id,
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return 'user';
    }

    public function getScopes(): array
    {
        return ['read', 'write'];
    }

    public function getRoles(): array
    {
        return ['admin'];
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->getScopes(), true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }
}

final class TimingInterceptor implements InterceptorInterface
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

// --- Test Controller ---

final class PipelineTestController
{
    public function index(): array
    {
        return ['message' => 'ok'];
    }

    public function greet(): array
    {
        return ['greeting' => 'hello world'];
    }

    public function throwsException(): never
    {
        throw new \RuntimeException('Controller error');
    }

    public function throwsProblemDetails(): never
    {
        throw new ProblemDetailsException(
            ProblemDetails::forbidden('Not allowed'),
        );
    }
}

// --- Integration Tests ---

final class PipelineWiringTest extends TestCase
{
    /**
     * Test: Guard blocks request -> 403
     */
    public function test_guard_blocks_request_returns_403(): void
    {
        $context = $this->createContext();

        $denyGuard = new AlwaysDenyGuard();

        $config = new PipelineConfig(
            guards: [$denyGuard],
        );

        $executor = new PipelineExecutor();

        $this->expectException(ForbiddenException::class);

        $executor->execute($context, fn() => Response::json(['ok' => true]), $config);
    }

    /**
     * Test: Guard allows request -> controller executes
     */
    public function test_guard_allows_request_controller_executes(): void
    {
        $context = $this->createContext();

        $allowGuard = new AlwaysAllowGuard();

        $config = new PipelineConfig(
            guards: [$allowGuard],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute($context, fn() => Response::json(['message' => 'ok']), $config);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->statusCode);
        $this->assertSame(['message' => 'ok'], $result->body);
    }

    /**
     * Test: Header-based guard checks a specific header value
     */
    public function test_header_guard_blocks_when_header_missing(): void
    {
        $request = new Request('GET', '/test', [], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
        );

        $guard = new HeaderCheckGuard('X-Api-Key', 'secret-key');

        $config = new PipelineConfig(
            guards: [$guard],
        );

        $executor = new PipelineExecutor();

        $this->expectException(ForbiddenException::class);

        $executor->execute($context, fn() => Response::json(['ok' => true]), $config);
    }

    /**
     * Test: Header-based guard allows when header matches
     */
    public function test_header_guard_allows_when_header_matches(): void
    {
        $request = new Request('GET', '/test', ['X-Api-Key' => 'secret-key'], [], null);
        $context = new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
        );

        $guard = new HeaderCheckGuard('X-Api-Key', 'secret-key');

        $config = new PipelineConfig(
            guards: [$guard],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute($context, fn() => Response::json(['ok' => true]), $config);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->statusCode);
    }

    /**
     * Test: Interceptor wraps controller (before + after)
     */
    public function test_interceptor_wraps_controller_before_and_after(): void
    {
        $context = $this->createContext();

        $interceptor = new TimingInterceptor();

        $config = new PipelineConfig(
            interceptors: [$interceptor],
        );

        $executor = new PipelineExecutor();
        $executor->execute($context, fn() => Response::json(['ok' => true]), $config);

        $this->assertSame('before', $interceptor->beforeCalled);
        $this->assertSame('after', $interceptor->afterCalled);
    }

    /**
     * Test: Interceptor adds a response header
     */
    public function test_interceptor_adds_response_header(): void
    {
        $context = $this->createContext();

        $interceptor = new ResponseHeaderInterceptor('X-Request-Id', 'abc-123');

        $config = new PipelineConfig(
            interceptors: [$interceptor],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute($context, fn() => Response::json(['ok' => true]), $config);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('abc-123', $result->headers['X-Request-Id']);
    }

    /**
     * Test: CORS preflight -> 200 with headers (via HttpKernel)
     */
    public function test_cors_preflight_returns_200_with_headers(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type', 'Authorization'],
            maxAge: 3600,
        );

        $corsGuard = new CorsGuard($corsConfig);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $class) use ($corsGuard): bool {
            return $class === CorsGuard::class;
        });
        $container->method('get')->willReturnCallback(function (string $class) use ($corsGuard): mixed {
            if ($class === CorsGuard::class) {
                return $corsGuard;
            }
            return null;
        });

        $router = new Router();

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
            globalGuardClasses: [CorsGuard::class],
        );

        $request = new Request('OPTIONS', '/api/items', ['Origin' => 'https://example.com'], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(204, $response->statusCode);
        $this->assertSame('https://example.com', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST', $response->headers['Access-Control-Allow-Methods']);
        $this->assertSame('Content-Type, Authorization', $response->headers['Access-Control-Allow-Headers']);
        $this->assertSame('3600', $response->headers['Access-Control-Max-Age']);
    }

    /**
     * Test: CORS preflight returns default headers when no CorsGuard registered
     */
    public function test_cors_preflight_returns_default_headers_when_no_cors_guard(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $router = new Router();

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('OPTIONS', '/api/items', ['Origin' => 'https://example.com'], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('https://example.com', $response->headers['Access-Control-Allow-Origin']);
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $response->headers);
    }

    /**
     * Test: ProblemDetailsFilter catches exception -> RFC 9457 response
     */
    public function test_problem_details_filter_catches_exception(): void
    {
        $context = $this->createContext();

        $filter = new ProblemDetailsFilter();

        $config = new PipelineConfig(
            filters: [$filter],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute(
            $context,
            fn() => throw new ProblemDetailsException(ProblemDetails::forbidden('Not allowed')),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('application/problem+json', $result->headers['Content-Type']);
        $this->assertSame(403, $result->body['status']);
        $this->assertSame('Forbidden', $result->body['title']);
        $this->assertSame('Not allowed', $result->body['detail']);
        $this->assertArrayHasKey('correlationId', $result->body);
    }

    /**
     * Test: ProblemDetailsFilter handles generic exceptions as 500
     */
    public function test_problem_details_filter_handles_generic_exception_as_500(): void
    {
        $context = $this->createContext();

        $filter = new ProblemDetailsFilter();

        $config = new PipelineConfig(
            filters: [$filter],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute(
            $context,
            fn() => throw new \RuntimeException('Something broke'),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(500, $result->statusCode);
        $this->assertSame('application/problem+json', $result->headers['Content-Type']);
        $this->assertSame(500, $result->body['status']);
        $this->assertSame('Internal Server Error', $result->body['title']);
    }

    /**
     * Test: Custom exception filter catches specific exceptions
     */
    public function test_custom_exception_filter_catches_forbidden(): void
    {
        $context = $this->createContext();

        $denyGuard = new AlwaysDenyGuard();
        $filter = new TestExceptionFilter();

        $config = new PipelineConfig(
            guards: [$denyGuard],
            filters: [$filter],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute(
            $context,
            fn() => Response::json(['ok' => true]),
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('Access Denied by Filter', $result->body['error']);
    }

    /**
     * Test: Full pipeline with auth guard + interceptor + controller + error filter
     */
    public function test_full_pipeline_with_auth_guard_interceptor_controller_filter(): void
    {
        $principal = new TestPrincipal(42);
        $context = $this->createContext();

        // Auth guard sets principal
        $authGuard = new PrincipalSettingGuard($principal);

        // Interceptor adds response header
        $interceptor = new ResponseHeaderInterceptor('X-Powered-By', 'LatticePHP');

        // Filter (fallback)
        $filter = new ProblemDetailsFilter();

        $config = new PipelineConfig(
            guards: [$authGuard],
            interceptors: [$interceptor],
            filters: [$filter],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute(
            $context,
            function (ExecutionContextInterface $ctx) {
                // Controller can access principal
                $principal = $ctx->getPrincipal();
                $this->assertNotNull($principal);
                $this->assertSame(42, $principal->getId());

                return Response::json(['user_id' => $principal->getId()]);
            },
            $config,
        );

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->statusCode);
        $this->assertSame(['user_id' => 42], $result->body);
        $this->assertSame('LatticePHP', $result->headers['X-Powered-By']);
    }

    /**
     * Test: Full pipeline where controller throws and filter catches
     */
    public function test_full_pipeline_controller_throws_filter_catches(): void
    {
        $context = $this->createContext();

        $allowGuard = new AlwaysAllowGuard();
        $interceptor = new ResponseHeaderInterceptor('X-Request-Id', 'req-001');
        $filter = new ProblemDetailsFilter();

        $config = new PipelineConfig(
            guards: [$allowGuard],
            interceptors: [$interceptor],
            filters: [$filter],
        );

        $executor = new PipelineExecutor();
        $result = $executor->execute(
            $context,
            fn() => throw new ProblemDetailsException(ProblemDetails::badRequest('Invalid input')),
            $config,
        );

        // ProblemDetailsFilter now returns a Response with problem+json body
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(400, $result->statusCode);
        $this->assertSame('application/problem+json', $result->headers['Content-Type']);
        $this->assertSame('Bad Request', $result->body['title']);
        $this->assertSame('Invalid input', $result->body['detail']);
    }

    /**
     * Test: Global guards run before route guards via HttpKernel
     */
    public function test_global_guards_execute_before_route_guards_via_http_kernel(): void
    {
        $executionOrder = [];

        // We test this by having a global guard and route guard that record execution order
        // Since we can't easily use closures as guard class names for HttpKernel (it resolves from class names),
        // we test the ordering logic through the PipelineExecutor directly with the merged array.
        $globalGuard = new class($executionOrder) implements GuardInterface {
            private array $order;

            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function canActivate(ExecutionContextInterface $context): bool
            {
                $this->order[] = 'global';
                return true;
            }
        };

        $routeGuard = new class($executionOrder) implements GuardInterface {
            private array $order;

            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            public function canActivate(ExecutionContextInterface $context): bool
            {
                $this->order[] = 'route';
                return true;
            }
        };

        $context = $this->createContext();

        // Simulate what HttpKernel does: global guards first, then route guards
        $config = new PipelineConfig(
            guards: [$globalGuard, $routeGuard],
        );

        $executor = new PipelineExecutor();
        $executor->execute($context, fn() => 'ok', $config);

        $this->assertSame(['global', 'route'], $executionOrder);
    }

    /**
     * Test: Principal can be set by guard and read by subsequent code
     */
    public function test_principal_set_by_guard_readable_in_context(): void
    {
        $principal = new TestPrincipal('user-99');
        $request = new Request('GET', '/test', [], [], null);

        $context = new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
        );

        // Initially no principal
        $this->assertNull($context->getPrincipal());

        // Guard sets principal
        $context->setPrincipal($principal);

        // Principal is now available
        $this->assertNotNull($context->getPrincipal());
        $this->assertSame('user-99', $context->getPrincipal()->getId());
        $this->assertSame('user', $context->getPrincipal()->getType());
        $this->assertTrue($context->getPrincipal()->hasScope('read'));
        $this->assertTrue($context->getPrincipal()->hasRole('admin'));
    }

    /**
     * Test: Multiple guards in sequence - first failure stops the chain
     */
    public function test_multiple_guards_first_failure_stops_chain(): void
    {
        $context = $this->createContext();

        $allow = new AlwaysAllowGuard();
        $deny = new AlwaysDenyGuard();
        $neverReached = new AlwaysAllowGuard();

        $config = new PipelineConfig(
            guards: [$allow, $deny, $neverReached],
        );

        $executor = new PipelineExecutor();

        $this->expectException(ForbiddenException::class);

        $executor->execute($context, fn() => 'should not reach', $config);
    }

    /**
     * Test: Pipe transforms data
     */
    public function test_pipe_transforms_data(): void
    {
        $pipe = new UppercasePipe();
        $pipeChain = new PipeChain();

        $result = $pipeChain->execute([$pipe], 'hello world', []);

        $this->assertSame('HELLO WORLD', $result);
    }

    /**
     * Test: Full HttpKernel pipeline execution with route-level guards
     */
    public function test_http_kernel_executes_pipeline_with_route_guards(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
            parameterBindings: [],
            guards: [AlwaysDenyGuard::class],
        );

        $router = new Router();
        $router->addRoute($route);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')->willReturnCallback(function (string $class): object {
            return new $class();
        });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/test', [], [], null);
        $response = $kernel->handle($request);

        // ProblemDetailsFilter is auto-appended by HttpKernel, so ForbiddenException
        // is caught and converted to a 403 RFC 9457 response
        $this->assertSame(403, $response->statusCode);
    }

    /**
     * Test: HttpKernel with route guard allowing request
     */
    public function test_http_kernel_route_guard_allows_request(): void
    {
        $route = new RouteDefinition(
            httpMethod: 'GET',
            path: '/test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
            parameterBindings: [],
            guards: [AlwaysAllowGuard::class],
        );

        $router = new Router();
        $router->addRoute($route);

        $controller = new PipelineTestController();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('make')->willReturnCallback(function (string $class) use ($controller): object {
            if ($class === PipelineTestController::class) {
                return $controller;
            }
            return new $class();
        });

        $kernel = new HttpKernel(
            router: $router,
            container: $container,
            parameterResolver: new ParameterResolver(),
            exceptionHandler: new ExceptionHandler(),
        );

        $request = new Request('GET', '/test', [], [], null);
        $response = $kernel->handle($request);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['message' => 'ok'], $response->body);
    }

    private function createContext(): HttpExecutionContext
    {
        $request = new Request('GET', '/test', [], [], null);

        return new HttpExecutionContext(
            request: $request,
            module: 'test',
            controllerClass: PipelineTestController::class,
            methodName: 'index',
        );
    }
}
