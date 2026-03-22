<?php

declare(strict_types=1);

namespace Lattice\Grpc;

interface GrpcServiceInterface
{
    public function getName(): string;

    /** @return array<GrpcMethod> */
    public function getMethods(): array;
}
