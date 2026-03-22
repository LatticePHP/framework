<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Modules\Companies\CompanyResource;
use Lattice\Http\Resource;

final class ContactResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'source' => $this->resource->source,
            'tags' => $this->resource->tags,
            'company_id' => $this->resource->company_id,
            'owner_id' => $this->resource->owner_id,
            'company' => $this->whenLoaded('company', fn ($company) => CompanyResource::make($company)->toArray()),
            'deals_count' => $this->when(
                $this->resource->relationLoaded('deals'),
                fn () => $this->resource->deals->count(),
            ),
            'activities_count' => $this->when(
                $this->resource->relationLoaded('activities'),
                fn () => $this->resource->activities->count(),
            ),
            'notes_count' => $this->when(
                $this->resource->relationLoaded('notes'),
                fn () => $this->resource->notes->count(),
            ),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
