<?php

declare(strict_types=1);

namespace Lattice\JsonApi;

final class JsonApiResource
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly array $attributes = [],
        public readonly array $relationships = [],
        public readonly array $links = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'id' => $this->id,
            'attributes' => $this->attributes,
        ];

        if ($this->relationships !== []) {
            $data['relationships'] = $this->relationships;
        }

        if ($this->links !== []) {
            $data['links'] = $this->links;
        }

        if ($this->meta !== []) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
