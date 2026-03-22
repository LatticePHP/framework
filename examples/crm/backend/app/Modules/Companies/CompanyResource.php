<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use Lattice\Http\Resource;

final class CompanyResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'domain' => $this->resource->domain,
            'industry' => $this->resource->industry,
            'size' => $this->resource->size,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'address' => $this->resource->address,
            'city' => $this->resource->city,
            'state' => $this->resource->state,
            'country' => $this->resource->country,
            'website' => $this->resource->website,
            'owner_id' => $this->resource->owner_id,
            'contacts_count' => $this->when(
                $this->resource->relationLoaded('contacts'),
                fn () => $this->resource->contacts->count(),
            ),
            'deals_count' => $this->when(
                $this->resource->relationLoaded('deals'),
                fn () => $this->resource->deals->count(),
            ),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
