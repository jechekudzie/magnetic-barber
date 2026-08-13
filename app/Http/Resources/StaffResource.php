<?php

namespace App\Http\Resources;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The public view of a barber. Deliberately carries no phone number or email:
 * staff contact details are not public data.
 *
 * @mixin StaffProfile
 */
class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user?->ulid,
            'slug' => $this->slug,
            'name' => $this->name(),
            'title' => $this->title,
            'bio' => $this->bio,
            'specialities' => $this->specialities ?? [],
            'instagram_handle' => $this->instagram_handle,
            'photo_url' => $this->photo_path ? Storage::url($this->photo_path) : null,
            'accepts_house_calls' => $this->accepts_house_calls,
            'is_bookable' => $this->is_bookable,
            'rating' => [
                'average' => $this->rating_avg,
                'count' => $this->rating_count,
            ],
        ];
    }
}
