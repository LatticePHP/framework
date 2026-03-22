<?php

declare(strict_types=1);

namespace Lattice\Grpc;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;

final class GrpcExecutionContext implements ExecutionContextInterface
{
    public function __construct(
        private readonly string $module,
        private readonly string $class,
        private readonly string $method,
        private readonly string $correlationId,
        private readonly ?PrincipalInterface $principal = null,
    ) {}

    public function getType(): ExecutionType
    {
        return ExecutionType::Grpc;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getHandler(): string
    {
        return $this->class . '::' . $this->method;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getPrincipal(): ?PrincipalInterface
    {
        return $this->principal;
    }
}
