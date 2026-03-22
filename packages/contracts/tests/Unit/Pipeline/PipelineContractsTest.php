<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Pipeline;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PipelineContractsTest extends TestCase
{
    #[Test]
    public function guardInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(GuardInterface::class));
    }

    #[Test]
    public function guardInterfaceHasCanActivateMethod(): void
    {
        $reflection = new ReflectionClass(GuardInterface::class);

        $this->assertTrue($reflection->hasMethod('canActivate'));

        $method = $reflection->getMethod('canActivate');
        $this->assertSame('bool', $method->getReturnType()->getName());

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame(ExecutionContextInterface::class, $params[0]->getType()->getName());
    }

    #[Test]
    public function guardCanBeImplemented(): void
    {
        $guard = new class implements GuardInterface {
            public function canActivate(ExecutionContextInterface $context): bool
            {
                return true;
            }
        };

        $context = $this->createMock(ExecutionContextInterface::class);
        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function pipeInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(PipeInterface::class));
    }

    #[Test]
    public function pipeInterfaceHasTransformMethod(): void
    {
        $reflection = new ReflectionClass(PipeInterface::class);

        $this->assertTrue($reflection->hasMethod('transform'));

        $method = $reflection->getMethod('transform');
        $this->assertSame('mixed', $method->getReturnType()->getName());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('value', $params[0]->getName());
        $this->assertSame('metadata', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
    }

    #[Test]
    public function pipeCanBeImplemented(): void
    {
        $pipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return strtoupper((string) $value);
            }
        };

        $this->assertSame('HELLO', $pipe->transform('hello'));
    }

    #[Test]
    public function interceptorInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(InterceptorInterface::class));
    }

    #[Test]
    public function interceptorInterfaceHasInterceptMethod(): void
    {
        $reflection = new ReflectionClass(InterceptorInterface::class);

        $this->assertTrue($reflection->hasMethod('intercept'));

        $method = $reflection->getMethod('intercept');
        $this->assertSame('mixed', $method->getReturnType()->getName());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame(ExecutionContextInterface::class, $params[0]->getType()->getName());
        $this->assertSame('callable', $params[1]->getType()->getName());
    }

    #[Test]
    public function interceptorCanBeImplemented(): void
    {
        $interceptor = new class implements InterceptorInterface {
            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                return $next();
            }
        };

        $context = $this->createMock(ExecutionContextInterface::class);
        $result = $interceptor->intercept($context, fn () => 'intercepted');
        $this->assertSame('intercepted', $result);
    }

    #[Test]
    public function exceptionFilterInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ExceptionFilterInterface::class));
    }

    #[Test]
    public function exceptionFilterInterfaceHasCatchMethod(): void
    {
        $reflection = new ReflectionClass(ExceptionFilterInterface::class);

        $this->assertTrue($reflection->hasMethod('catch'));

        $method = $reflection->getMethod('catch');
        $this->assertSame('mixed', $method->getReturnType()->getName());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('Throwable', $params[0]->getType()->getName());
        $this->assertSame(ExecutionContextInterface::class, $params[1]->getType()->getName());
    }

    #[Test]
    public function exceptionFilterCanBeImplemented(): void
    {
        $filter = new class implements ExceptionFilterInterface {
            public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
            {
                return ['error' => $exception->getMessage()];
            }
        };

        $context = $this->createMock(ExecutionContextInterface::class);
        $result = $filter->catch(new \RuntimeException('test error'), $context);
        $this->assertSame(['error' => 'test error'], $result);
    }
}
