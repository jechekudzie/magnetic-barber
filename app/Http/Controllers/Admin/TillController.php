<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The till, and the queue of money somebody says they sent.
 *
 * EcoCash and InnBucks settle outside this system, so a client paying from
 * the app records a reference and a screenshot rather than a settlement.
 * Their chair is held immediately — which is the trade the shop agreed to —
 * and this is the screen where somebody checks whether the money arrived.
 *
 * Until this existed, clients were sending proofs the website could not see.
 */
class TillController extends AdminController
{
    public function index(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 403, 'You are not assigned to a branch.');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = $branch->timezone ?: config('magnetic.timezone');

        $day = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $timezone)
            : CarbonImmutable::now($timezone);

        $from = $day->startOfDay()->utc();
        $to = $day->endOfDay()->utc();

        /*
         * Everything still waiting on somebody, whatever day it was claimed.
         * A proof sent on Friday night that nobody looked at must not fall
         * off the screen on Saturday morning.
         */
        $awaiting = Payment::query()
            ->where('branch_id', $branch->id)
            ->awaitingCheck()
            ->with(['appointment.client', 'appointment.services'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Payment $payment): array => $this->shape($payment))
            ->values();

        $taken = Payment::query()
            ->where('branch_id', $branch->id)
            ->counted()
            ->whereBetween('verified_at', [$from, $to])
            ->with(['appointment.client', 'recordedBy'])
            ->latest('verified_at')
            ->get();

        // Today's bookings that still owe something, so the desk can take it
        // without hunting through the diary.
        $owing = Appointment::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->whereColumn('amount_paid_cents', '<', 'total_cents')
            ->with(['client', 'services', 'staff'])
            ->orderBy('scheduled_start_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->ulid,
                'reference' => $appointment->reference,
                'client' => $appointment->client->name,
                'barber' => $appointment->staff?->name,
                'when' => $appointment->whenLabel(),
                'status' => $appointment->status->label(),
                'services' => $appointment->services->pluck('name_snapshot')->all(),
                'total' => $appointment->total()->toArray(),
                'paid' => $appointment->amountPaid()->toArray(),
                'outstanding' => $appointment->outstanding()->toArray(),
                'is_secured' => $appointment->isSecured(),
            ])
            ->values();

        return inertia('admin/till', [
            'branchContext' => $this->branchContext($request),
            'date' => $day->toDateString(),
            'awaiting' => $awaiting,
            'owing' => $owing,
            'takings' => [
                'total' => Money::ofCents(
                    (int) $taken->sum('amount_cents'),
                    config('magnetic.reporting_currency'),
                )->toArray(),
                'count' => $taken->count(),
                'by_method' => $taken
                    ->groupBy(fn (Payment $payment): string => $payment->method->value)
                    ->map(fn ($group, string $method): array => [
                        'method' => $method,
                        'label' => PaymentMethod::from($method)->label(),
                        'count' => $group->count(),
                        'total' => Money::ofCents(
                            (int) $group->sum('amount_cents'),
                            config('magnetic.reporting_currency'),
                        )->toArray(),
                    ])
                    ->values()
                    ->all(),
            ],
            'methods' => array_map(
                fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'needs_proof' => $method->needsProof(),
                ],
                PaymentMethod::cases(),
            ),
            'can' => [
                'take' => $request->user()->can('payment.take'),
                'void' => $request->user()->can('sale.void'),
            ],
        ]);
    }

    /**
     * Take money against a booking.
     */
    public function store(
        Request $request,
        Appointment $appointment,
        PaymentService $payments,
    ): RedirectResponse {
        $this->assertSameBranch($request, $appointment);

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $method = PaymentMethod::from($validated['method']);

        if ($method->needsProof() && blank($validated['reference'] ?? null)) {
            return back()->withErrors([
                'reference' => 'A '.$method->label().' payment needs its transaction reference.',
            ]);
        }

        $payments->record(
            $appointment,
            [
                'method' => $method,
                'amount' => Money::of((float) $validated['amount'], $appointment->currency),
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
            ],
            recordedBy: $request->user(),
        );

        $outstanding = $appointment->refresh()->outstanding();

        return back()->with('success', $outstanding->isZero()
            ? "{$appointment->reference} is paid in full."
            : "Recorded. {$outstanding->format()} still outstanding on {$appointment->reference}.");
    }

    /**
     * The money arrived, or the screenshot holds up.
     */
    public function confirm(
        Request $request,
        Payment $payment,
        PaymentService $payments,
    ): RedirectResponse {
        $this->assertPaymentHere($request, $payment);

        $payments->confirm($payment, $request->user());

        return back()->with('success', "{$payment->amount()->format()} confirmed.");
    }

    /**
     * Checked and wrong. The row stays: a client who insists they paid is
     * exactly who this record is for.
     */
    public function reject(
        Request $request,
        Payment $payment,
        PaymentService $payments,
    ): RedirectResponse {
        $this->assertPaymentHere($request, $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $payments->reject($payment, $request->user(), $validated['reason']);

        return back()->with('success', 'Marked as not received.');
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Payment $payment): array
    {
        return [
            'id' => $payment->ulid,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'status' => $payment->status->value,
            'amount' => $payment->amount()->toArray(),
            'reference' => $payment->reference,
            'proof_url' => $payment->proofUrl(),
            'note' => $payment->note,
            'claimed_at' => $payment->created_at?->toIso8601String(),
            'self_recorded' => $payment->recorded_by === null,
            'appointment' => [
                'id' => $payment->appointment->ulid,
                'reference' => $payment->appointment->reference,
                'client' => $payment->appointment->client->name,
                'when' => $payment->appointment->whenLabel(),
                'total' => $payment->appointment->total()->toArray(),
                'services' => $payment->appointment->services->pluck('name_snapshot')->all(),
            ],
        ];
    }

    private function assertSameBranch(Request $request, Appointment $appointment): void
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null || $appointment->branch_id !== $branch->id, 404);
    }

    private function assertPaymentHere(Request $request, Payment $payment): void
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null || $payment->branch_id !== $branch->id, 404);

        abort_if(
            $payment->status !== PaymentStatus::Pending,
            422,
            'That payment has already been dealt with.',
        );
    }
}
