<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use App\Support\UploadedImage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Taking money, and knowing whether it arrived.
 *
 * EcoCash and InnBucks settle outside this system. There is no callback to
 * trust, so what the shop records is the client's word plus a reference and a
 * screenshot, and somebody at the desk checks it afterwards.
 *
 * That gap is the whole design. A booking is secured the moment money is
 * recorded against it, because a client who pays at nine at night should have
 * their chair; whether the shop has actually been paid is a second, later
 * question, and the two are tracked separately rather than conflated into one
 * "paid" flag that means neither.
 */
final class PaymentService
{
    /**
     * Record money against a booking.
     *
     * Cash and card taken at the desk are confirmed on the spot — the person
     * recording them is holding the money. Anything that settles elsewhere
     * starts pending, whoever entered it.
     *
     * @param  array{method: PaymentMethod, amount: Money, reference?: string|null, note?: string|null, paid_at?: CarbonInterface|null}  $data
     */
    public function record(
        Appointment $appointment,
        array $data,
        ?User $recordedBy = null,
        ?UploadedFile $proof = null,
    ): Payment {
        $method = $data['method'];

        $takenInPerson = $recordedBy !== null && ! $method->needsProof();

        return DB::transaction(function () use ($appointment, $data, $recordedBy, $proof, $method, $takenInPerson): Payment {
            $payment = Payment::create([
                'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id,
                'method' => $method,
                'status' => $takenInPerson ? PaymentStatus::Confirmed : PaymentStatus::Pending,
                'amount_cents' => $data['amount']->cents,
                'currency' => $data['amount']->currency,
                'reference' => $data['reference'] ?? null,
                'proof_path' => $proof === null
                    ? null
                    : UploadedImage::store($proof, 'payment-proofs'),
                'paid_at' => $data['paid_at'] ?? now(),
                'recorded_by' => $recordedBy?->id,
                // takenInPerson is only true when somebody recorded it, so
                // there is always a verifier on this branch.
                'verified_by' => $takenInPerson ? $recordedBy->id : null,
                'verified_at' => $takenInPerson ? now() : null,
                'note' => $data['note'] ?? null,
            ]);

            $this->resettle($appointment);

            return $payment;
        });
    }

    /**
     * Somebody at the shop has seen the money or the proof.
     */
    public function confirm(Payment $payment, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $by): Payment {
            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->resettle($payment->appointment);

            return $payment->refresh();
        });
    }

    /**
     * Checked and wrong: no such transaction, or the wrong amount.
     *
     * The row stays. A rejected payment is the record of somebody having
     * claimed to pay, which is exactly what the shop needs when the client
     * turns up insisting they did.
     */
    public function reject(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $payment->update([
                'status' => PaymentStatus::Rejected,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->resettle($payment->appointment);

            return $payment->refresh();
        });
    }

    /**
     * What this booking has actually been paid, and whether its chair is
     * spoken for.
     *
     * amount_paid counts confirmed money only. secured_at is set by anything
     * not yet rejected, because the client has done their part and the chair
     * is theirs while the shop catches up.
     */
    public function resettle(Appointment $appointment): void
    {
        $confirmed = (int) $appointment->payments()->counted()->sum('amount_cents');

        $hasLiveClaim = $appointment->payments()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value])
            ->exists();

        $appointment->forceFill([
            'amount_paid_cents' => $confirmed,
            // Set once and left alone: the moment a chair was first spoken
            // for is history, not a value that should flicker.
            'secured_at' => $hasLiveClaim ? ($appointment->secured_at ?? now()) : null,
        ])->save();
    }

    /**
     * What a client has to send to secure this booking.
     *
     * The full price, which for a from-priced service is its starting figure
     * — the rest is settled in the chair once the colour is chosen.
     */
    public function dueFor(Appointment $appointment): Money
    {
        $total = Money::ofCents($appointment->total_cents, $appointment->currency);
        $paid = Money::ofCents($appointment->amount_paid_cents, $appointment->currency);

        $outstanding = $total->minus($paid);

        return $outstanding->cents > 0
            ? $outstanding
            : Money::ofCents(0, $appointment->currency);
    }

    /**
     * The desk's queue: money claimed but not yet checked.
     *
     * @return Collection<int, Payment>
     */
    public function awaitingCheck(int $branchId)
    {
        return Payment::query()
            ->where('branch_id', $branchId)
            ->awaitingCheck()
            ->with(['appointment.client', 'appointment.services'])
            ->orderBy('created_at')
            ->get();
    }
}
