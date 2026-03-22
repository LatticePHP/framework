<?php

declare(strict_types=1);

namespace Lattice\Contracts\Context;

interface ExecutionContextInterface
{
    public function getType(): ExecutionType;

    public function getModule(): string;

    public function getHandler(): string;

    public function getClass(): string;

    public function getMethod(): string;

    public function getCorrelationId(): string;

    public function getPrincipal(): ?PrincipalInterface;
}
