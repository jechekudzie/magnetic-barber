<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
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
            'tagline' => $this->tagline,
            'phone' => $this->phone,
            'phone_display' => Phone::forDisplay($this->phone),
            'whatsapp' => $this->whatsapp,
            'whatsapp_link' => $this->whatsapp
                ? 'https://wa.me/'.Phone::forWhatsAppLink($this->whatsapp)
                : null,
            'email' => $this->email,
            'address' => [
                'line' => $this->address_line,
                'area' => $this->area,
                'city' => $this->city,
                'directions_note' => $this->directions_note,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'map_url' => $this->mapUrl(),
            ],
            'hours' => [
                'timezone' => $this->timezone,
                'opens_at' => substr((string) $this->opens_at, 0, 5),
                'closes_at' => substr((string) $this->closes_at, 0, 5),
                'days_open' => $this->days_open ?? [],
            ],
            'chair_count' => $this->chair_count,
            'house_call_enabled' => $this->house_call_enabled,
            'house_call_radius_km' => $this->house_call_radius_km,
        ];
    }
}
