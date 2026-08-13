<?php

namespace App\Http\Resources;

use App\Models\Style;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Style
 */
class StyleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'gender_tag' => $this->gender_tag?->value,
            'gender_label' => $this->gender_tag?->label(),
            'hair_type_tag' => $this->hair_type_tag ?? [],
            'typical_duration_minutes' => $this->typical_duration_minutes,
            'image_url' => $this->image_path ? Storage::url($this->image_path) : null,
            'is_featured' => $this->is_featured,
            'service' => new ServiceResource($this->whenLoaded('service')),
        ];
    }
}
