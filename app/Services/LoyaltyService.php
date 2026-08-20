<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LoyaltyLedger;
use App\Models\LoyaltyRule;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Points.
 *
 * The balance is always a SUM over the ledger, never a stored column. That is
 * the whole design: an append only ledger can be audited and replayed, a
 * mutable balance drifts and then cannot be explained to the client holding it.
 */
final class LoyaltyService
{
    public function balanceFor(User $client): int
    {
        return (int) LoyaltyLedger::query()
            ->where('client_id', $client->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');
    }

    /**
     * Points for a completed visit. Idempotent: the unique index on
     * (appointment_id, type) means completing the same visit twice cannot pay
     * out twice, and this returns the existing row instead of throwing.
     */
    public function awardForVisit(Appointment $appointment): ?LoyaltyLedger
    {
        if ($appointment->status !== AppointmentStatus::Completed) {
            return null;
        }

        $rule = LoyaltyRule::current();

        $points = $rule->points_per_visit
            + (int) floor(($appointment->total_cents / 100) * $rule->points_per_currency_unit);

        if ($points < 1) {
            return null;
        }

        return DB::transaction(function () use ($appointment, $points, $rule): LoyaltyLedger {
            $already = LoyaltyLedger::query()
                ->where('appointment_id', $appointment->id)
                ->where('type', 'earn')
                ->lockForUpdate()
                ->first();

            if ($already !== null) {
                return $already;
            }

            $client = $appointment->client;

            return LoyaltyLedger::create([
                'client_id' => $client->id,
                'branch_id' => $appointment->branch_id,
                'appointment_id' => $appointment->id,
                'type' => 'earn',
                'points' => $points,
                'balance_after' => $this->balanceFor($client) + $points,
                'description' => "Visit {$appointment->reference}",
                'expires_at' => $rule->points_expiry_months
                    ? now()->addMonths($rule->points_expiry_months)
                    : null,
            ]);
        });
    }

    /**
     * A manual correction, always attributed to whoever made it.
     */
    public function adjust(User $client, int $points, string $reason, ?int $byUserId = null): LoyaltyLedger
    {
        return DB::transaction(fn (): LoyaltyLedger => LoyaltyLedger::create([
            'client_id' => $client->id,
            'type' => $points >= 0 ? 'adjust' : 'redeem',
            'points' => $points,
            'balance_after' => $this->balanceFor($client) + $points,
            'description' => $reason,
            'created_by' => $byUserId,
        ]));
    }

    /**
     * What this client can take off a bill of this size, in whole reward
     * blocks. Never more than the bill itself: points buy a cut, not credit,
     * and a negative total is a refund nobody authorised.
     *
     * @return array{points: int, discount: Money}|null
     */
    public function plannedRedemption(User $client, Money $bill): ?array
    {
        $rule = LoyaltyRule::current();
        $threshold = max(1, $rule->redemption_threshold);

        if ($rule->redemption_value_cents < 1) {
            return null;
        }

        $blocks = min(
            intdiv($this->balanceFor($client), $threshold),
            intdiv($bill->cents, $rule->redemption_value_cents),
        );

        if ($blocks < 1) {
            return null;
        }

        return [
            'points' => $blocks * $threshold,
            'discount' => Money::ofCents(
                $blocks * $rule->redemption_value_cents,
                $rule->currency,
            ),
        ];
    }

    /**
     * Spend points against a booking. Negative points, so the balance is still
     * a plain SUM over the ledger and the redemption can be explained later.
     */
    public function redeem(User $client, int $points, Appointment $appointment, ?int $byUserId = null): LoyaltyLedger
    {
        return LoyaltyLedger::create([
            'client_id' => $client->id,
            'branch_id' => $appointment->branch_id,
            'appointment_id' => $appointment->id,
            'type' => 'redeem',
            'points' => -abs($points),
            'balance_after' => $this->balanceFor($client) - abs($points),
            'description' => "Redeemed against {$appointment->reference}",
            'created_by' => $byUserId,
        ]);
    }

    /**
     * What a balance is worth right now, and whether it can be spent yet.
     *
     * @return array{points: int, redeemable: bool, threshold: int, value: array<string, mixed>, reward: array<string, mixed>, to_next: int}
     */
    public function summaryFor(User $client): array
    {
        $rule = LoyaltyRule::current();
        $points = $this->balanceFor($client);
        $threshold = max(1, $rule->redemption_threshold);

        return [
            'points' => $points,
            'redeemable' => $points >= $rule->redemption_threshold,
            'threshold' => $rule->redemption_threshold,
            'value' => Money::ofCents($rule->valueOfCents($points), $rule->currency)->toArray(),
            // What one block off the bill is worth, so a screen can work out
            // the discount against a given basket without guessing.
            'reward' => $rule->redemptionValue()->toArray(),
            'to_next' => $points >= $threshold ? 0 : $threshold - $points,
        ];
    }
}
