<?php

declare(strict_types=1);

namespace Lattice\Pipeline;

use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;

final class PipelineConfig
{
    /**
     * @param array<GuardInterface> $guards
     * @param array<PipeInterface> $pipes
     * @param array<InterceptorInterface> $interceptors
     * @param array<ExceptionFilterInterface> $filters
     * @param array<class-string<GuardInterface>> $guardClasses
     * @param array<class-string<PipeInterface>> $pipeClasses
     * @param array<class-string<InterceptorInterface>> $interceptorClasses
     * @param array<class-string<ExceptionFilterInterface>> $filterClasses
     */
    public function __construct(
        private readonly array $guards = [],
        private readonly array $pipes = [],
        private readonly array $interceptors = [],
        private readonly array $filters = [],
        private readonly array $guardClasses = [],
        private readonly array $pipeClasses = [],
        private readonly array $interceptorClasses = [],
        private readonly array $filterClasses = [],
    ) {}

    /** @return array<GuardInterface> */
    public function getGuards(): array
    {
        return $this->guards;
    }

    /** @return array<PipeInterface> */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /** @return array<InterceptorInterface> */
    public function getInterceptors(): array
    {
        return $this->interceptors;
    }

    /** @return array<ExceptionFilterInterface> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @return array<class-string<GuardInterface>> */
    public function getGuardClasses(): array
    {
        return $this->guardClasses;
    }

    /** @return array<class-string<PipeInterface>> */
    public function getPipeClasses(): array
    {
        return $this->pipeClasses;
    }

    /** @return array<class-string<InterceptorInterface>> */
    public function getInterceptorClasses(): array
    {
        return $this->interceptorClasses;
    }

    /** @return array<class-string<ExceptionFilterInterface>> */
    public function getFilterClasses(): array
    {
        return $this->filterClasses;
    }
}
