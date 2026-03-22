<?php

declare(strict_types=1);

namespace Lattice\Pipeline;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Pipeline\Filter\FilterChain;
use Lattice\Pipeline\Guard\GuardChain;
use Lattice\Pipeline\Interceptor\InterceptorChain;
use Lattice\Pipeline\Pipe\PipeChain;

final class PipelineExecutor
{
    private readonly GuardChain $guardChain;
    private readonly InterceptorChain $interceptorChain;
    private readonly PipeChain $pipeChain;
    private readonly FilterChain $filterChain;

    public function __construct(
        ?GuardChain $guardChain = null,
        ?InterceptorChain $interceptorChain = null,
        ?PipeChain $pipeChain = null,
        ?FilterChain $filterChain = null,
    ) {
        $this->guardChain = $guardChain ?? new GuardChain();
        $this->interceptorChain = $interceptorChain ?? new InterceptorChain();
        $this->pipeChain = $pipeChain ?? new PipeChain();
        $this->filterChain = $filterChain ?? new FilterChain();
    }

    /**
     * Execute the full pipeline: guards -> interceptors(before) -> pipes -> handler -> interceptors(after) -> filters(on error).
     */
    public function execute(ExecutionContextInterface $context, callable $handler, PipelineConfig $config): mixed
    {
        try {
            // 1. Run guards
            $this->guardChain->execute($config->getGuards(), $context);

            // 2. Wrap handler with interceptors. Inside the interceptor-wrapped handler,
            //    pipes run before the actual handler call.
            $pipedHandler = function () use ($config, $handler, $context): mixed {
                // Run pipes (transform step)
                if (!empty($config->getPipes())) {
                    $this->pipeChain->execute($config->getPipes(), null, []);
                }

                return $handler($context);
            };

            // 3. Run through interceptor chain
            return $this->interceptorChain->execute(
                $config->getInterceptors(),
                $context,
                $pipedHandler,
            );
        } catch (\Throwable $exception) {
            // 4. Route to exception filters
            if (!empty($config->getFilters())) {
                return $this->filterChain->handle($exception, $context, $config->getFilters());
            }

            throw $exception;
        }
    }
}
