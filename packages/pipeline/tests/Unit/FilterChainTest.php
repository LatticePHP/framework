<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Pipeline\Filter\FilterChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterChainTest extends TestCase
{
    private FilterChain $chain;

    protected function setUp(): void
    {
        $this->chain = new FilterChain();
    }

    #[Test]
    public function it_rethrows_when_no_filters_registered(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $exception = new \RuntimeException('test error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $this->chain->handle($exception, $context, []);
    }

    #[Test]
    public function it_routes_exception_to_matching_filter(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $exception = new \InvalidArgumentException('bad input');

        $filter = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                return 'handled: ' . $exception->getMessage();
            }
        };

        $result = $this->chain->handle($exception, $context, [$filter]);

        $this->assertSame('handled: bad input', $result);
    }

    #[Test]
    public function it_tries_filters_in_order_and_uses_first_that_does_not_rethrow(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $exception = new \RuntimeException('error');

        $filter1 = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                throw $exception; // re-throw, doesn't handle
            }
        };

        $filter2 = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                return 'caught by filter2';
            }
        };

        $result = $this->chain->handle($exception, $context, [$filter1, $filter2]);

        $this->assertSame('caught by filter2', $result);
    }

    #[Test]
    public function it_rethrows_if_all_filters_rethrow(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $exception = new \RuntimeException('unhandled');

        $filter = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                throw $exception;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unhandled');

        $this->chain->handle($exception, $context, [$filter]);
    }
}
