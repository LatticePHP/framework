<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Fixtures;

use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Response;

class SimpleController
{
    public function index(): array
    {
        return ['message' => 'hello'];
    }

    public function show(string $id): array
    {
        return ['id' => $id];
    }

    public function create(array $data): array
    {
        return ['created' => $data];
    }

    public function throwError(): never
    {
        throw new \RuntimeException('Something went wrong');
    }

    public function notFound(): never
    {
        throw new NotFoundException('Item not found');
    }

    public function customResponse(): Response
    {
        return Response::json(['status' => 'created'], 201);
    }
}
