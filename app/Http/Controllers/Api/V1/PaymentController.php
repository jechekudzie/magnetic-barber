<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\PaymentResource;
use App\Models\Appointment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A client paying for their own booking.
 *
 * EcoCash and InnBucks settle outside this system, so there is nothing to
 * call back and confirm. The client pays on their phone, then tells us the
 * reference and sends the screenshot. The chair is theirs from that moment;
 * the desk checks the proof afterwards.
 */
class PaymentController extends Controller
{
    /**
     * What the client may pay with themselves. Cash is a desk method.
     *
     * @return list<string>
     */
    private function selfServiceMethods(): array
    {
        return array_values(array_map(
            fn (PaymentMethod $method): string => $method->value,
            array_filter(PaymentMethod::cases(), fn (PaymentMethod $m): bool => $m->isSelfService()),
        ));
    }

    /**
     * What is owed, and how it can be sent.
     */
    public function show(Request $request, Appointment $appointment, PaymentService $payments): JsonResponse
    {
        $this->assertOwnedBy($request, $appointment);

        return response()->json([
            'due' => $payments->dueFor($appointment)->toArray(),
            'total' => Money::ofCents($appointment->total_cents, $appointment->currency)->toArray(),
            'paid' => $appointment->amountPaid()->toArray(),
            'is_secured' => $appointment->isSecured(),
            'methods' => array_map(
                fn (string $value): array => [
                    'value' => $value,
                    'label' => PaymentMethod::from($value)->label(),
                    'needs_proof' => PaymentMethod::from($value)->needsProof(),
                ],
                $this->selfServiceMethods(),
            ),
            'data' => PaymentResource::collection($appointment->payments()->latest()->get()),
        ]);
    }

    /**
     * Tell the shop you have paid, with the reference and the screenshot.
     */
    public function store(Request $request, Appointment $appointment, PaymentService $payments): JsonResponse
    {
        $this->assertOwnedBy($request, $appointment);

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in($this->selfServiceMethods())],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reference' => ['required', 'string', 'max:60'],
            // The screenshot. Capped so a phone photo cannot fill the disk.
            'proof' => ['nullable', 'image', 'max:8192'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'reference.required' => 'We need the transaction reference from your phone.',
            'proof.max' => 'That image is too large. Eight megabytes is the limit.',
        ]);

        $payment = $payments->record(
            $appointment,
            [
                'method' => PaymentMethod::from($validated['method']),
                'amount' => Money::ofCents((int) $validated['amount_cents'], $appointment->currency),
                'reference' => $validated['reference'],
                'note' => $validated['note'] ?? null,
            ],
            // Null: the client recorded this themselves, which is exactly the
            // case the desk has to check.
            recordedBy: null,
            proof: $request->file('proof'),
        );

        return response()->json([
            'data' => new PaymentResource($payment),
            'appointment' => new AppointmentResource(
                $appointment->refresh()->load(['branch', 'staff.staffProfile', 'services', 'payments'])
            ),
            'message' => 'Thank you. Your chair is held while we check it.',
        ], 201);
    }

    /**
     * A booking that is not yours should look like one that does not exist.
     */
    private function assertOwnedBy(Request $request, Appointment $appointment): void
    {
        abort_if($appointment->client_id !== $request->user()->id, 404);
    }
}
