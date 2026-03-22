<?php

declare(strict_types=1);

namespace App\Modules\Deals;

use App\Modules\Companies\CompanyResource;
use App\Modules\Contacts\ContactResource;
use Lattice\Http\Resource;

final class DealResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'value' => (float) $this->resource->value,
            'currency' => $this->resource->currency,
            'stage' => $this->resource->stage,
            'probability' => $this->resource->probability,
            'expected_close_date' => $this->resource->expected_close_date?->toDateString(),
            'actual_close_date' => $this->resource->actual_close_date?->toDateString(),
            'lost_reason' => $this->resource->lost_reason,
            'is_closed' => $this->resource->isClosed(),
            'is_won' => $this->resource->isWon(),
            'contact_id' => $this->resource->contact_id,
            'company_id' => $this->resource->company_id,
            'owner_id' => $this->resource->owner_id,
            'contact' => $this->whenLoaded('contact', fn ($c) => ContactResource::make($c)->toArray()),
            'company' => $this->whenLoaded('company', fn ($c) => CompanyResource::make($c)->toArray()),
            'activities_count' => $this->when(
                $this->resource->relationLoaded('activities'),
                fn () => $this->resource->getRelation('activities')?->count() ?? 0,
            ),
            'notes_count' => $this->when(
                $this->resource->relationLoaded('notes'),
                fn () => $this->resource->getRelation('notes')?->count() ?? 0,
            ),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
