<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'session_count' => $this->session_count,
            'price' => $this->price()->toArray(),
            'validity_days' => $this->validity_days,
            'perks' => $this->perks ?? [],
            'is_popular' => $this->is_popular,
        ];
    }
}
