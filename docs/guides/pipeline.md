# Request Pipeline

## Overview

Every HTTP request in LatticePHP flows through a four-stage pipeline before reaching your controller. The `PipelineExecutor` orchestrates the stages in this exact order:

```
Guards -> Interceptors(before) -> Pipes -> Controller -> Interceptors(after) -> Filters(on error)
```

If a guard denies access, execution stops. If the controller throws, exception filters catch it. Interceptors wrap the controller in an onion model -- code runs before AND after.

## Pipeline Architecture

`Lattice\Pipeline\PipelineExecutor` is the core engine:

```php
final class PipelineExecutor
{
    public function execute(ExecutionContextInterface $context, callable $handler, PipelineConfig $config): mixed
    {
        try {
            // 1. Run guards -- all must pass
            $this->guardChain->execute($config->getGuards(), $context);

            // 2. Wrap handler: pipes run inside interceptor chain
            $pipedHandler = function () use ($config, $handler, $context): mixed {
                if (!empty($config->getPipes())) {
                    $this->pipeChain->execute($config->getPipes(), null, []);
                }
                return $handler($context);
            };

            // 3. Run through interceptor onion
            return $this->interceptorChain->execute($config->getInterceptors(), $context, $pipedHandler);
        } catch (\Throwable $exception) {
            // 4. Route to exception filters
            if (!empty($config->getFilters())) {
                return $this->filterChain->handle($exception, $context, $config->getFilters());
            }
            throw $exception;
        }
    }
}
```

## How HttpKernel Wires the Pipeline

`Lattice\Http\HttpKernel::handle()` builds the pipeline for every request:

1. Matches the route via `Router::match()`
2. Creates an `HttpExecutionContext` with request, controller class, and method name
3. Resolves guard/interceptor/pipe/filter class names from the DI container
4. Merges global guards before route guards: `array_merge($globalGuards, $routeGuards)`
5. Appends `ProblemDetailsFilter` as the fallback error handler
6. Passes everything to `PipelineExecutor::execute()`

```php
$config = new PipelineConfig(
    guards: $guards,
    pipes: $pipes,
    interceptors: $interceptors,
    filters: $filters,
);

$executor = new PipelineExecutor(
    guardChain: new GuardChain(),
    interceptorChain: new InterceptorChain(),
    pipeChain: new PipeChain(),
    filterChain: new FilterChain(),
);

$result = $executor->execute($context, $handler, $config);
```

## Guards

Guards implement `GuardInterface` and decide whether a request can proceed. Return `true` to allow, `false` to deny.

```php
final class JwtAuthenticationGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        $token = $context->getRequest()->bearerToken();
        if ($token === null) return false;

        $claims = $this->encoder->decode($token, ...);
        $context->setPrincipal(new Principal(...));
        return true;
    }
}
```

### GuardChain Execution

`GuardChain::execute()` runs guards in sequence. ALL must return `true`. The first `false` throws `ForbiddenException` (403) and stops the chain:

```php
final class GuardChain
{
    public function execute(array $guards, ExecutionContextInterface $context): bool
    {
        foreach ($guards as $guard) {
            if (!$guard->canActivate($context)) {
                throw new ForbiddenException();
            }
        }
        return true;
    }
}
```

### Guard Ordering

Global guards run BEFORE route guards. This is guaranteed by `HttpKernel`:

```php
$guards = array_merge($globalGuards, $routeGuards);
```

From the integration test proving this:

```php
// Order log captures: ['global', 'route']
$config = new PipelineConfig(guards: [$globalGuard, $routeGuard]);
$executor->execute($context, fn () => 'ok', $config);
$this->assertSame(['global', 'route'], $order);
```

### Applying Guards with #[UseGuards]

```php
use Lattice\Pipeline\Attributes\UseGuards;

#[Get('/me')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
public function me(#[CurrentUser] Principal $user): Response { ... }
```

## Interceptors

Interceptors implement `InterceptorInterface` and wrap the controller call in an onion model. They can run logic before and after the handler:

```php
final class LoggingInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        // BEFORE controller
        $this->log[] = 'request:' . $context->getHandler();

        $result = $next($context);  // Call the next layer (or controller)

        // AFTER controller
        $this->log[] = 'response:' . $result->statusCode;
        return $result;
    }
}
```

### Onion Ordering

With interceptors `[A, B]`, execution flows as `A.before -> B.before -> handler -> B.after -> A.after`:

```php
$log = [];
$interceptorA = new MCIOnionInterceptor($log, 'A');
$interceptorB = new MCIOnionInterceptor($log, 'B');

$config = new PipelineConfig(interceptors: [$interceptorA, $interceptorB]);
$executor->execute($context, function () use (&$log) {
    $log[] = 'handler';
    return 'result';
}, $config);

// Result: ['A.before', 'B.before', 'handler', 'B.after', 'A.after']
```

The `InterceptorChain` builds this by wrapping from inside out:

```php
$pipeline = $handler;
foreach (array_reverse($interceptors) as $interceptor) {
    $pipeline = fn($ctx) => $interceptor->intercept($ctx, $next);
}
return $pipeline($context);
```

### Response Modification

Interceptors can modify responses after the controller runs:

```php
final class ResponseHeaderInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $result = $next($context);
        if ($result instanceof Response) {
            return $result->withHeader('X-Custom', 'value');
        }
        return $result;
    }
}
```

## Pipes

Pipes implement `PipeInterface` and transform input data before the controller executes:

```php
final class TrimPipe implements PipeInterface
{
    public function transform(mixed $value, array $metadata = []): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }
}
```

Apply with `#[UsePipes]`:

```php
#[Post('/contacts')]
#[UsePipes(pipes: [TrimPipe::class, StripTagsPipe::class])]
public function create(#[Body] CreateContactDto $dto): Response { ... }
```

## Exception Filters

Filters implement `ExceptionFilterInterface` and catch exceptions thrown by the controller or earlier pipeline stages:

```php
final class CustomExceptionFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        return Response::json([
            'error' => $exception->getMessage(),
            'type' => get_class($exception),
        ], 500);
    }
}
```

### ProblemDetailsFilter (Default)

`HttpKernel` automatically appends `ProblemDetailsFilter` as the last filter on every route. It formats errors as RFC 9457 Problem Details:

```php
$result = $executor->execute(
    $context,
    fn () => throw new \RuntimeException('Unhandled error'),
    new PipelineConfig(filters: [new ProblemDetailsFilter()]),
);

// Response: 500, Content-Type: application/problem+json
// Body: { "status": 500, "title": "Internal Server Error", "correlationId": "..." }
```

For HTTP exceptions like `ForbiddenException`, it preserves the correct status code:

```php
// ForbiddenException -> 403, detail: "Access denied"
$this->assertSame(403, $result->statusCode);
$this->assertSame('Access denied', $result->body['detail']);
```

## CORS Handling

`HttpKernel` intercepts OPTIONS requests with an `Origin` header before routing. If a `CorsGuard` is registered as a global guard, it handles the preflight:

```php
$corsGuard = new CorsGuard(new CorsConfig(
    allowedOrigins: ['https://app.example.com'],
));
```

A disallowed origin causes the `CorsGuard` to deny the request (403). For non-preflight requests, CORS runs as a normal guard in the chain.

## Rate Limiting

Apply the `#[RateLimit]` attribute to a controller. The `RateLimitGuard` reads the attribute and enforces limits:

```php
use Lattice\RateLimit\Attributes\RateLimit;

#[RateLimit(maxAttempts: 2, decaySeconds: 60, key: 'test-rate-limit')]
final class ApiController
{
    public function index(): array { return ['ok' => true]; }
}
```

From the integration test -- requests 1-2 pass, request 3 throws `ForbiddenException`:

```php
$executor->execute($context, $handler, $config); // 200
$executor->execute($context, $handler, $config); // 200
$executor->execute($context, $handler, $config); // ForbiddenException (over limit)
```

## Principal Flow Through the Pipeline

The principal (authenticated user) is set by a guard and flows through every subsequent layer:

```
Guard (sets principal) -> Interceptor (reads principal) -> Controller (reads via #[CurrentUser])
```

From the integration test:

```php
$authGuard = new MCIPrincipalSettingGuard($principal);
$interceptor = new MCIPrincipalReadingInterceptor();

$config = new PipelineConfig(guards: [$authGuard], interceptors: [$interceptor]);
$executor->execute($context, function (ExecutionContextInterface $ctx) {
    $controllerPrincipal = $ctx->getPrincipal(); // Available here
    return Response::json(['user_id' => $controllerPrincipal->getId()]);
}, $config);

// Guard sets it, interceptor reads it, controller reads it
$this->assertSame(42, $context->getPrincipal()->getId());
$this->assertSame(42, $interceptor->readPrincipal->getId());
```

## Full Pipeline Example

A real-world request flows through CORS, rate limiting, JWT auth, logging, and error handling:

```php
$config = new PipelineConfig(
    guards: [$corsGuard, $rateLimitGuard, $jwtGuard],
    interceptors: [$loggingInterceptor],
    filters: [$problemDetailsFilter],
);

$result = $executor->execute($context, function (ExecutionContextInterface $ctx) {
    return Response::json([
        'user_id' => $ctx->getPrincipal()->getId(),
        'items' => ['item1', 'item2'],
    ]);
}, $config);

// Guards ran: CORS allowed, rate limit passed, JWT decoded
// Interceptor logged: request + response
// Result: 200, user_id = 'user-99'
```
