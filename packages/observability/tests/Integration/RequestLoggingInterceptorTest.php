<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Observability\Log;
use Lattice\Observability\Logger\InMemoryLogHandler;
use Lattice\Observability\Logger\StructuredLogger;
use Lattice\Observability\Middleware\RequestLoggingInterceptor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class RequestLoggingInterceptorTest extends TestCase
{
    private InMemoryLogHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new InMemoryLogHandler();
        Log::setInstance(new StructuredLogger($this->handler));
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    #[Test]
    public function test_logs_successful_request(): void
    {
        $interceptor = new RequestLoggingInterceptor();
        $context = $this->createContext();

        $result = $interceptor->intercept($context, function (ExecutionContextInterface $ctx): string {
            return 'response-body';
        });

        $this->assertSame('response-body', $result);

        $entries = $this->handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(LogLevel::INFO, $entries[0]->level);
        $this->assertSame('Request handled', $entries[0]->message);
        $this->assertArrayHasKey('duration_ms', $entries[0]->context);
        $this->assertArrayHasKey('handler', $entries[0]->context);
        $this->assertArrayHasKey('module', $entries[0]->context);
        $this->assertArrayHasKey('correlation_id', $entries[0]->context);
    }

    #[Test]
    public function test_logs_failed_request(): void
    {
        $interceptor = new RequestLoggingInterceptor();
        $context = $this->createContext();

        $exception = new \RuntimeException('Something went wrong');

        try {
            $interceptor->intercept($context, function () use ($exception): never {
                throw $exception;
            });
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $entries = $this->handler->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(LogLevel::ERROR, $entries[0]->level);
        $this->assertSame('Request failed', $entries[0]->message);
        $this->assertSame('Something went wrong', $entries[0]->context['error']);
        $this->assertSame(\RuntimeException::class, $entries[0]->context['error_class']);
        $this->assertArrayHasKey('duration_ms', $entries[0]->context);
    }

    #[Test]
    public function test_duration_is_recorded(): void
    {
        $interceptor = new RequestLoggingInterceptor();
        $context = $this->createContext();

        $interceptor->intercept($context, function (ExecutionContextInterface $ctx): string {
            // Simulate some work
            usleep(1000); // 1ms
            return 'ok';
        });

        $entries = $this->handler->getEntries();
        $this->assertGreaterThan(0, $entries[0]->context['duration_ms']);
    }

    #[Test]
    public function test_returns_result_from_next(): void
    {
        $interceptor = new RequestLoggingInterceptor();
        $context = $this->createContext();

        $result = $interceptor->intercept($context, function (ExecutionContextInterface $ctx): array {
            return ['status' => 'ok', 'data' => [1, 2, 3]];
        });

        $this->assertSame(['status' => 'ok', 'data' => [1, 2, 3]], $result);
    }

    #[Test]
    public function test_context_includes_handler_info(): void
    {
        $interceptor = new RequestLoggingInterceptor();
        $context = $this->createContext('UserController', 'index', 'user-module');

        $interceptor->intercept($context, fn () => 'ok');

        $entry = $this->handler->getEntries()[0];
        $this->assertSame('UserController::index', $entry->context['handler']);
        $this->assertSame('user-module', $entry->context['module']);
    }

    private function createContext(
        string $class = 'TestController',
        string $method = 'handle',
        string $module = 'test-module',
    ): ExecutionContextInterface {
        return new class($class, $method, $module) implements ExecutionContextInterface {
            public function __construct(
                private readonly string $class,
                private readonly string $method,
                private readonly string $module,
            ) {}

            public function getType(): ExecutionType
            {
                return ExecutionType::Http;
            }

            public function getModule(): string
            {
                return $this->module;
            }

            public function getHandler(): string
            {
                return $this->class . '::' . $this->method;
            }

            public function getClass(): string
            {
                return $this->class;
            }

            public function getMethod(): string
            {
                return $this->method;
            }

            public function getCorrelationId(): string
            {
                return 'corr-test-123';
            }

            public function getPrincipal(): ?PrincipalInterface
            {
                return null;
            }
        };
    }
}
