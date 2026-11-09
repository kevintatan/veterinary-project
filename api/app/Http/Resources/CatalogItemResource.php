<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Any mixed-catalog row. `is_global` tells the UI whether it can be edited.
 */
class CatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return collect($this->resource->attributesToArray())
            ->except(['clinic_id', 'created_at', 'updated_at'])
            ->put('is_global', $this->resource->clinic_id === null)
            ->all();
    }
}
