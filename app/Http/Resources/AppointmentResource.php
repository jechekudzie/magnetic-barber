<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'type' => $this->type->value,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'when_label' => $this->whenLabel(),
            'duration_minutes' => $this->duration_minutes,
            'total' => Money::ofCents($this->total_cents, $this->currency)->toArray(),
            'client_note' => $this->client_note,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'slug' => $this->branch->slug,
                'name' => $this->branch->name,
                'address_line' => $this->branch->address_line,
                'area' => $this->branch->area,
                'map_url' => $this->branch->mapUrl(),
            ]),
            'staff' => $this->whenLoaded('staff', fn (): ?array => $this->staff === null ? null : [
                'name' => $this->staff->staffProfile?->name() ?? $this->staff->name,
                'title' => $this->staff->staffProfile?->title,
            ]),
            'travel_fee' => Money::ofCents($this->travel_fee_cents, $this->currency)->toArray(),
            'house_call' => $this->whenLoaded('houseCall', fn (): ?array => $this->houseCall === null ? null : [
                'address' => $this->houseCall->fullAddress(),
                'directions_note' => $this->houseCall->directions_note,
                'travel_fee' => $this->houseCall->travelFee()->toArray(),
            ]),
            'services' => $this->whenLoaded('services', fn (): array => $this->services
                ->map(fn (AppointmentService $line): array => [
                    'name' => $line->name_snapshot,
                    'duration_minutes' => $line->duration_minutes,
                    'price' => $line->price()->toArray(),
                ])
                ->all()),
        ];
    }
}
