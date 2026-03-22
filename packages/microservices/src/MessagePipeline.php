<?php

declare(strict_types=1);

namespace Lattice\Microservices;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use RuntimeException;

final class MessagePipeline
{
    /**
     * @param array<GuardInterface> $guards
     * @param array<PipeInterface> $pipes
     * @param array<InterceptorInterface> $interceptors
     */
    public function __construct(
        private readonly array $guards = [],
        private readonly array $pipes = [],
        private readonly array $interceptors = [],
    ) {}

    public function process(MessageEnvelopeInterface $envelope, callable $handler): mixed
    {
        // Build execution context for guards/interceptors
        $context = new MessageExecutionContext(
            module: 'microservices',
            class: 'anonymous',
            method: 'handle',
            correlationId: $envelope->getCorrelationId(),
        );

        // Run guards
        foreach ($this->guards as $guard) {
            if (!$guard->canActivate($context)) {
                throw new RuntimeException('Guard denied access');
            }
        }

        // Run pipes to transform envelope
        $transformed = $envelope;
        foreach ($this->pipes as $pipe) {
            $transformed = $pipe->transform($transformed);
        }

        // Build interceptor chain wrapping the handler
        $core = fn () => $handler($transformed);

        $chain = $core;
        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = $chain;
            $chain = fn () => $interceptor->intercept($context, fn () => $next());
        }

        return $chain();
    }
}
