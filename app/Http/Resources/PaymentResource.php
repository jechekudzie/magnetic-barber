<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => $this->amount()->toArray(),
            'reference' => $this->reference,
            'proof_url' => $this->proofUrl(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'note' => $this->note,
            'recorded_by' => $this->whenLoaded('recordedBy', fn (): ?string => $this->recordedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
