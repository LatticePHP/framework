<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Interceptor;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;

final class InterceptorChain
{
    /**
     * Wrap handler in onion layers of interceptors.
     *
     * @param array<InterceptorInterface> $interceptors
     */
    public function execute(array $interceptors, ExecutionContextInterface $context, callable $handler): mixed
    {
        // Build the onion from inside out: start with the handler,
        // then wrap each interceptor around it in reverse order.
        $pipeline = $handler;

        foreach (array_reverse($interceptors) as $interceptor) {
            $pipeline = (static function (InterceptorInterface $interceptor, callable $next) {
                return static function (ExecutionContextInterface $context) use ($interceptor, $next): mixed {
                    return $interceptor->intercept($context, $next);
                };
            })($interceptor, $pipeline);
        }

        return $pipeline($context);
    }
}
