<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Pipeline\Exceptions\ForbiddenException;
use Lattice\Pipeline\PipelineConfig;
use Lattice\Pipeline\PipelineExecutor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipelineExecutorTest extends TestCase
{
    private PipelineExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new PipelineExecutor();
    }

    #[Test]
    public function it_executes_handler_with_empty_config(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $config = new PipelineConfig();

        $result = $this->executor->execute($context, fn () => 'done', $config);

        $this->assertSame('done', $result);
    }

    #[Test]
    public function it_blocks_when_guard_rejects(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('canActivate')->willReturn(false);

        $config = new PipelineConfig(guards: [$guard]);

        $this->expectException(ForbiddenException::class);

        $this->executor->execute($context, fn () => 'should not reach', $config);
    }

    #[Test]
    public function it_runs_interceptors_wrapping_handler(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $log = [];

        $interceptor = new class ($log) implements InterceptorInterface {
            public function __construct(private array &$log) {}

            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $this->log[] = 'before';
                $result = $next($context);
                $this->log[] = 'after';
                return $result;
            }
        };

        $config = new PipelineConfig(interceptors: [$interceptor]);

        $result = $this->executor->execute($context, function () use (&$log) {
            $log[] = 'handler';
            return 'value';
        }, $config);

        $this->assertSame('value', $result);
        $this->assertSame(['before', 'handler', 'after'], $log);
    }

    #[Test]
    public function it_runs_pipes_before_handler(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $log = [];

        $pipe = new class ($log) implements PipeInterface {
            public function __construct(private array &$log) {}

            public function transform(mixed $value, array $metadata = []): mixed
            {
                $this->log[] = 'pipe';
                return $value;
            }
        };

        $config = new PipelineConfig(pipes: [$pipe]);

        $result = $this->executor->execute($context, function () use (&$log) {
            $log[] = 'handler';
            return 'result';
        }, $config);

        $this->assertSame('result', $result);
        $this->assertSame(['pipe', 'handler'], $log);
    }

    #[Test]
    public function it_catches_exceptions_with_filters(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $filter = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                return 'recovered: ' . $exception->getMessage();
            }
        };

        $config = new PipelineConfig(filters: [$filter]);

        $result = $this->executor->execute($context, function () {
            throw new \RuntimeException('boom');
        }, $config);

        $this->assertSame('recovered: boom', $result);
    }

    #[Test]
    public function it_rethrows_when_no_filter_handles_exception(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $config = new PipelineConfig();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->executor->execute($context, function () {
            throw new \RuntimeException('boom');
        }, $config);
    }

    #[Test]
    public function it_runs_full_pipeline_in_correct_order(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $log = [];

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('canActivate')->willReturnCallback(function () use (&$log) {
            $log[] = 'guard';
            return true;
        });

        $interceptor = new class ($log) implements InterceptorInterface {
            public function __construct(private array &$log) {}

            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $this->log[] = 'interceptor:before';
                $result = $next($context);
                $this->log[] = 'interceptor:after';
                return $result;
            }
        };

        $pipe = new class ($log) implements PipeInterface {
            public function __construct(private array &$log) {}

            public function transform(mixed $value, array $metadata = []): mixed
            {
                $this->log[] = 'pipe';
                return $value;
            }
        };

        $config = new PipelineConfig(
            guards: [$guard],
            pipes: [$pipe],
            interceptors: [$interceptor],
        );

        $result = $this->executor->execute($context, function () use (&$log) {
            $log[] = 'handler';
            return 'ok';
        }, $config);

        $this->assertSame('ok', $result);
        $this->assertSame([
            'guard',
            'interceptor:before',
            'pipe',
            'handler',
            'interceptor:after',
        ], $log);
    }

    #[Test]
    public function guard_failure_is_caught_by_filter(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $guard = $this->createMock(GuardInterface::class);
        $guard->method('canActivate')->willReturn(false);

        $filter = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                return 'forbidden handled';
            }
        };

        $config = new PipelineConfig(guards: [$guard], filters: [$filter]);

        $result = $this->executor->execute($context, fn () => 'nope', $config);

        $this->assertSame('forbidden handled', $result);
    }
}
