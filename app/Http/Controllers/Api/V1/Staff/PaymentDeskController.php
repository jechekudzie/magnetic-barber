<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The desk taking money, and checking money somebody says they sent.
 *
 * Cash and card are confirmed the moment they are recorded, because whoever
 * is typing is holding the money. EcoCash, InnBucks and bank transfers start
 * pending whoever enters them: the shop has a screenshot, not a settlement.
 */
class PaymentDeskController extends Controller
{
    /**
     * Record a payment against a booking. This is the till.
     */
    public function store(
        Request $request,
        Appointment $appointment,
        PaymentService $payments,
    ): JsonResponse {
        abort_unless($request->user()->can('payment.take'), 403);
        $this->assertSameBranch($appointment);

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reference' => ['nullable', 'string', 'max:60'],
            'proof' => ['nullable', 'image', 'max:8192'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $method = PaymentMethod::from($validated['method']);

        if ($method->needsProof() && blank($validated['reference'] ?? null)) {
            abort(422, 'A '.$method->label().' payment needs its transaction reference.');
        }

        $payment = $payments->record(
            $appointment,
            [
                'method' => $method,
                'amount' => Money::ofCents((int) $validated['amount_cents'], $appointment->currency),
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
            ],
            recordedBy: $request->user(),
            proof: $request->file('proof'),
        );

        return response()->json([
            'data' => new PaymentResource($payment),
            'outstanding' => $appointment->refresh()->outstanding()->toArray(),
        ], 201);
    }

    /**
     * The queue of money claimed but not yet checked.
     */
    public function awaitingCheck(Request $request, PaymentService $payments): JsonResponse
    {
        abort_unless($request->user()->can('payment.take'), 403);

        $branch = $this->branch();

        return response()->json([
            'data' => PaymentResource::collection($payments->awaitingCheck($branch)),
        ]);
    }

    /**
     * Seen the money, or seen the screenshot and it is good.
     */
    public function confirm(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        abort_unless($request->user()->can('payment.take'), 403);
        abort_if($payment->branch_id !== $this->branch(), 404);

        return response()->json([
            'data' => new PaymentResource($payments->confirm($payment, $request->user())),
        ]);
    }

    /**
     * Checked and wrong. The row stays, because a client who insists they
     * paid is exactly who this record is for.
     */
    public function reject(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        // Voiding money is a heavier right than taking it.
        abort_unless($request->user()->can('sale.void'), 403);
        abort_if($payment->branch_id !== $this->branch(), 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        return response()->json([
            'data' => new PaymentResource(
                $payments->reject($payment, $request->user(), $validated['reason'])
            ),
        ]);
    }

    private function branch(): int
    {
        abort_unless(app()->bound('currentBranch'), 403, 'You are not assigned to a branch.');

        return app('currentBranch')->id;
    }

    private function assertSameBranch(Appointment $appointment): void
    {
        abort_if($appointment->branch_id !== $this->branch(), 404);
    }
}
