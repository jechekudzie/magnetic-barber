<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Price is only present when the service was loaded through a branch, because
 * a service has no price of its own.
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = $this->priceForLoadedBranch();

        return [
            'id' => $this->ulid,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->durationForLoadedBranch(),
            'price' => $price?->toArray(),
            'is_free' => $price?->isZero() ?? false,
            'requires_patch_test' => $this->requires_patch_test,
            'patch_test_lead_hours' => $this->patch_test_lead_hours,
            'is_skin_service' => $this->is_skin_service,
            'is_house_call_eligible' => $this->is_house_call_eligible,
            'is_featured' => $this->is_featured,
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
        ];
    }
}
