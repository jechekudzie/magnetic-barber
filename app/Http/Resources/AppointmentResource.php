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
            /*
             * Whether the client may still call this off themselves. Set by
             * the caller rather than computed here, because it depends on a
             * cut off the resource has no business knowing about. Absent when
             * nobody asked, so the website is unaffected.
             */
            'client_may_cancel' => $this->whenNotNull($this->resource->getAttribute('client_may_cancel')),
            'total' => Money::ofCents($this->total_cents, $this->currency)->toArray(),
            'amount_paid' => $this->amountPaid()->toArray(),
            'outstanding' => $this->outstanding()->toArray(),
            // A chair is spoken for once money is claimed, before anybody has
            // checked it. Whether the shop has been paid is is_paid_in_full.
            'is_secured' => $this->isSecured(),
            'is_paid_in_full' => $this->isPaidInFull(),
            'minutes_so_far' => $this->minutesSoFar(),
            'is_overrunning' => $this->isOverrunning(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'client_note' => $this->client_note,
            'staff_note' => $this->staff_note,
            'client' => $this->whenLoaded('client', fn (): ?array => $this->client === null ? null : [
                'name' => $this->client->name,
                // Barbers deliberately cannot read client phone numbers: it is
                // how the shop keeps its client list when a barber leaves.
                'phone' => $request->user()?->can('client.contact.view')
                    ? $this->client->phone
                    : null,
                /*
                 * Only when the caller loaded it. Reaching for it otherwise
                 * is a lazy load, which this app refuses outside production —
                 * so a caller who forgot would get a 500 rather than a
                 * missing number.
                 */
                'visit_count' => $this->client->relationLoaded('clientProfile')
                    ? $this->client->clientProfile?->visit_count
                    : null,
            ]),
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
