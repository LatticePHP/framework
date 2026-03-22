<?php

declare(strict_types=1);

namespace App\Services;

use Lattice\Compiler\Attributes\Injectable;

/**
 * Example gRPC service implementing the Greeter proto definition.
 *
 * In a full implementation, this class would extend a generated
 * base class from the proto file and implement its RPC methods.
 */
#[Injectable]
final class GreeterService
{
    /**
     * Unary RPC: SayHello
     *
     * @param array{name: string} $request
     * @return array{message: string}
     */
    public function sayHello(array $request): array
    {
        $name = $request['name'] ?? 'World';

        return [
            'message' => 'Hello, ' . $name . '!',
        ];
    }

    /**
     * Server streaming RPC: SayHelloStream
     *
     * @param array{name: string, count: int} $request
     * @return iterable<array{message: string, index: int}>
     */
    public function sayHelloStream(array $request): iterable
    {
        $name = $request['name'] ?? 'World';
        $count = $request['count'] ?? 3;

        for ($i = 1; $i <= $count; $i++) {
            yield [
                'message' => "Hello, {$name}! (#{$i})",
                'index' => $i,
            ];
        }
    }
}
