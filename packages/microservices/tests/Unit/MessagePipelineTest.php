<?php

declare(strict_types=1);

namespace Lattice\Microservices\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Microservices\MessageEnvelope;
use Lattice\Microservices\MessagePipeline;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessagePipelineTest extends TestCase
{
    #[Test]
    public function it_processes_envelope_through_handler(): void
    {
        $pipeline = new MessagePipeline();
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: ['x' => 1]);

        $result = $pipeline->process($envelope, fn ($env) => $env->getPayload());

        $this->assertSame(['x' => 1], $result);
    }

    #[Test]
    public function it_runs_guards_before_handler(): void
    {
        $guard = new class implements GuardInterface {
            public function canActivate(ExecutionContextInterface $context): bool
            {
                return false;
            }
        };

        $pipeline = new MessagePipeline(guards: [$guard]);
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Guard denied access');

        $pipeline->process($envelope, fn ($env) => 'ok');
    }

    #[Test]
    public function it_runs_pipes_to_transform_envelope(): void
    {
        $pipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return $value;
            }
        };

        $pipeline = new MessagePipeline(pipes: [$pipe]);
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: ['v' => 1]);

        $result = $pipeline->process($envelope, fn ($env) => 'processed');

        $this->assertSame('processed', $result);
    }

    #[Test]
    public function it_runs_interceptors_wrapping_handler(): void
    {
        $log = [];
        $interceptor = new class($log) implements InterceptorInterface {
            public function __construct(private array &$log) {}

            public function intercept(ExecutionContextInterface $context, callable $next): mixed
            {
                $this->log[] = 'before';
                $result = $next($context);
                $this->log[] = 'after';
                return $result;
            }
        };

        $pipeline = new MessagePipeline(interceptors: [$interceptor]);
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: []);

        $result = $pipeline->process($envelope, fn ($env) => 'done');

        $this->assertSame('done', $result);
        $this->assertSame(['before', 'after'], $log);
    }

    #[Test]
    public function it_allows_passage_when_guard_permits(): void
    {
        $guard = new class implements GuardInterface {
            public function canActivate(ExecutionContextInterface $context): bool
            {
                return true;
            }
        };

        $pipeline = new MessagePipeline(guards: [$guard]);
        $envelope = new MessageEnvelope(messageId: '1', messageType: 'test', payload: []);

        $result = $pipeline->process($envelope, fn ($env) => 'allowed');

        $this->assertSame('allowed', $result);
    }
}
