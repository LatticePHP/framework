<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Http\Resource;

final class TestContactResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}
