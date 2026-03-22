<?php

declare(strict_types=1);

namespace App\Modules\Activities;

use Lattice\Http\Resource;

final class ActivityResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'title' => $this->resource->subject,
            'description' => $this->resource->description,
            'due_date' => $this->resource->due_date?->toIso8601String(),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            'is_overdue' => $this->resource->isOverdue(),
            'is_completed' => $this->resource->isCompleted(),
            'contact_id' => $this->resource->contact_id,
            'deal_id' => $this->resource->deal_id,
            'owner_id' => $this->resource->owner_id,
            'contact' => $this->whenLoaded('contact', fn ($c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
            ]),
            'deal' => $this->whenLoaded('deal', fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
