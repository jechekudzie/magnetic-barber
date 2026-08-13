<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'published_at' => $this->published_at?->toIso8601String(),
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'slug' => $this->branch->slug,
                'name' => $this->branch->name,
            ]),
        ];
    }
}
