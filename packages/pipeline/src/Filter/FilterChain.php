<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Filter;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;

final class FilterChain
{
    /**
     * Route exception through filters. First filter that handles it wins.
     * If a filter re-throws, the next filter is tried.
     * If no filter handles it, the original exception is re-thrown.
     *
     * @param array<ExceptionFilterInterface> $filters
     */
    public function handle(\Throwable $exception, ExecutionContextInterface $context, array $filters): mixed
    {
        foreach ($filters as $filter) {
            try {
                return $filter->catch($exception, $context);
            } catch (\Throwable) {
                // Filter re-threw or threw a new exception; try next filter.
                continue;
            }
        }

        throw $exception;
    }
}
