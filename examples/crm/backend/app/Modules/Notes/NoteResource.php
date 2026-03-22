<?php

declare(strict_types=1);

namespace App\Modules\Notes;

use Lattice\Http\Resource;

final class NoteResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'body' => $this->resource->content,
            'notable_type' => $this->resource->notable_type,
            'notable_id' => $this->resource->notable_id,
            'author_id' => $this->resource->author_id,
            'is_pinned' => $this->resource->is_pinned,
            'author' => $this->whenLoaded('author', fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
