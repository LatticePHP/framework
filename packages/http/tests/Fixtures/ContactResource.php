<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Fixtures;

use Lattice\Http\Resource;

final class ContactResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['first_name'] . ' ' . $this->resource['last_name'],
            'email' => $this->resource['email'],
        ];
    }
}
