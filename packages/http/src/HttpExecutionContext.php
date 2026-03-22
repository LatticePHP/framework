<?php

declare(strict_types=1);

namespace Lattice\Http;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;

final class HttpExecutionContext implements ExecutionContextInterface
{
    private readonly string $correlationId;
    private ?PrincipalInterface $principal;

    public function __construct(
        private readonly Request $request,
        private readonly string $module,
        private readonly string $controllerClass,
        private readonly string $methodName,
        ?PrincipalInterface $principal = null,
        ?string $correlationId = null,
    ) {
        $this->principal = $principal;
        $this->correlationId = $correlationId ?? bin2hex(random_bytes(16));
    }

    public function getType(): ExecutionType
    {
        return ExecutionType::Http;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getHandler(): string
    {
        return $this->controllerClass . '::' . $this->methodName;
    }

    public function getClass(): string
    {
        return $this->controllerClass;
    }

    public function getMethod(): string
    {
        return $this->methodName;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getPrincipal(): ?PrincipalInterface
    {
        return $this->principal;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Set the authenticated principal (called by auth guards during pipeline execution).
     */
    public function setPrincipal(?PrincipalInterface $principal): void
    {
        $this->principal = $principal;
    }
}
