<?php

declare(strict_types=1);

namespace Lattice\Grpc;

final class GrpcMethod
{
    /** @var callable */
    private $handler;

    public function __construct(
        public readonly string $serviceName,
        public readonly string $methodName,
        public readonly string $inputType,
        public readonly string $outputType,
        callable $handler,
    ) {
        $this->handler = $handler;
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }

    public function invoke(mixed $input): mixed
    {
        return ($this->handler)($input);
    }
}
