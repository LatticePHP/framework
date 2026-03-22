<?php

declare(strict_types=1);

namespace Lattice\Contracts\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function bind(string $abstract, mixed $concrete = null): void;

    public function singleton(string $abstract, mixed $concrete = null): void;

    public function instance(string $abstract, mixed $instance): void;

    public function make(string $abstract, array $parameters = []): mixed;

    public function reset(): void;
}
