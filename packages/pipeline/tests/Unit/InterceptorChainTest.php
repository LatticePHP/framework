<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Pipeline\Interceptor\InterceptorChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterceptorChainTest extends TestCase
{
    private InterceptorChain $chain;

    protected function setUp(): void
    {
        $this->chain = new InterceptorChain();
    }

    #[Test]
    public function it_executes_handler_with_no_interceptors(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $handler = fn () => 'result';

        $result = $this->chain->execute([], $context, $handler);

        $this->assertSame('result', $result);
    }

    #[Test]
    public function it_wraps_handler_with_single_interceptor(): void
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

        $handler = function () use (&$log) {
            $log[] = 'handler';
            return 'value';
        };

        $result = $this->chain->execute([$interceptor], $context, $handler);

        $this->assertSame('value', $result);
        $this->assertSame(['before', 'handler', 'after'], $log);
    }

    #[Test]
    public function it_wraps_handler_with_multiple_interceptors_in_onion_order(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $log = [];

        $interceptor1 = new class ($log) implements InterceptorInterface {
            public function __construct(private array &$log) {}

            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $this->log[] = 'A:before';
                $result = $next($context);
                $this->log[] = 'A:after';
                return $result;
            }
        };

        $interceptor2 = new class ($log) implements InterceptorInterface {
            public function __construct(private array &$log) {}

            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $this->log[] = 'B:before';
                $result = $next($context);
                $this->log[] = 'B:after';
                return $result;
            }
        };

        $handler = function () use (&$log) {
            $log[] = 'handler';
            return 42;
        };

        $result = $this->chain->execute([$interceptor1, $interceptor2], $context, $handler);

        $this->assertSame(42, $result);
        $this->assertSame(['A:before', 'B:before', 'handler', 'B:after', 'A:after'], $log);
    }

    #[Test]
    public function interceptor_can_modify_result(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $interceptor = new class implements InterceptorInterface {
            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $result = $next($context);
                return $result * 2;
            }
        };

        $handler = fn () => 21;

        $result = $this->chain->execute([$interceptor], $context, $handler);

        $this->assertSame(42, $result);
    }
}
