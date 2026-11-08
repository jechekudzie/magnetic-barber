<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\VisitActionException;
use App\Models\Appointment;
use App\Models\ClientProfile;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * A visit, from the moment somebody walks in to the moment they leave.
 *
 * This exists because the desk and the barber's phone were each doing it
 * their own way. Start and finish were written twice, and the two copies had
 * already drifted: finishing at the desk recorded the visit against the
 * client, finishing in the app did not, so a client's visit count depended on
 * which screen the shop happened to use that day. There is one copy now and
 * both call it.
 *
 * @see CommissionService for what the money does afterwards
 */
class VisitService
{
    /** A desk search is for finding one person, not for browsing. */
    private const MAX_HITS = 20;

    public function __construct(
        private CommissionService $commission,
        private LoyaltyService $loyalty,
        private ReminderService $reminders,
    ) {}

    /**
     * Find the person standing at the desk.
     *
     * Deliberately not scoped to a date. Somebody who booked online for
     * Thursday and turns up on Wednesday is still that booking, and a desk
     * that can only search today cannot help them. Ordered by how close the
     * booking is to now, because the one they mean is almost always the next
     * one.
     *
     * @param  array<int, int>  $branchIds
     * @return EloquentCollection<int, Appointment>
     */
    public function search(array $branchIds, string $term, ?int $limit = null): EloquentCollection
    {
        $term = trim($term);

        if ($term === '' || $branchIds === []) {
            return new EloquentCollection;
        }

        $cap = $limit ?? self::MAX_HITS;
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        // A number typed as 078..., 263... or +263... is the same client.
        $phone = Phone::normalise($term);
        $digits = preg_replace('/\D/', '', $term) ?? '';

        return Appointment::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function ($query) use ($term, $like, $phone, $digits): void {
                // A reference is read off a phone screen, so it is exact.
                $query->where('reference', strtoupper($term))
                    ->orWhereHas('client', function ($client) use ($like, $phone, $digits): void {
                        $client->where('name', 'like', $like);

                        if ($phone !== null) {
                            $client->orWhere('phone', $phone);
                        }

                        // The tail of the number, for anyone who only
                        // remembers the last few digits.
                        if (strlen($digits) >= 6) {
                            $client->orWhere('phone', 'like', '%'.substr($digits, -9));
                        }
                    });
            })
            ->with([
                'branch:id,name,slug,timezone',
                'client.clientProfile',
                'staff.staffProfile',
                'services',
                'payments',
                'houseCall',
            ])
            // Ordered in PHP, not SQL: "closest to now" wants an absolute
            // difference, and every portable way to write that is a raw
            // expression tied to one driver. Over-fetching the newest few
            // and sorting them costs nothing at this size and works
            // everywhere.
            ->orderByDesc('scheduled_start_at')
            ->limit($cap * 3)
            ->get()
            ->sortBy(fn (Appointment $appointment): int => abs(
                (int) $appointment->scheduled_start_at?->diffInMinutes(now())
            ))
            ->take($cap)
            ->values();
    }

    /**
     * Put this client in this barber's chair.
     *
     * The commission snapshot on each service line is rewritten to the new
     * barber, which looks like it contradicts the rule that snapshots are
     * never recomputed. It does not: that rule exists so a rate change next
     * month cannot rewrite work already done. Nothing has been done here yet,
     * and leaving the snapshot alone would pay a barber for a cut somebody
     * else is about to give.
     *
     * Once the visit is completed the money is settled and this refuses.
     */
    public function assign(Appointment $appointment, User $barber, User $actor): Appointment
    {
        $this->assertOpen($appointment, 'That visit is finished, so the chair cannot be changed.');

        if (! $barber->branches()->whereKey($appointment->branch_id)->exists()) {
            throw new VisitActionException($barber->name.' does not work at this branch.');
        }

        if ($appointment->staff_id === $barber->id) {
            return $appointment;
        }

        $this->assertFree($appointment, $barber);

        DB::transaction(function () use ($appointment, $barber, $actor): void {
            $appointment->update([
                'staff_id' => $barber->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);

            $rate = $this->commission->rateFor($barber, $appointment->branch);

            foreach ($appointment->services as $line) {
                $line->update([
                    'staff_id' => $barber->id,
                    'commission_rate' => $rate,
                    'commission_cents' => $this->commission->commissionOn(
                        $line->price(),
                        $rate,
                    )->cents,
                ]);
            }
        });

        return $appointment->refresh();
    }

    /**
     * Start the cut, and start the clock.
     *
     * The clock is not decoration: AvailabilityService reads it, so a cut
     * that runs past its slot keeps the chair blocked and nobody is sold a
     * time that is still warm.
     */
    public function start(Appointment $appointment, User $actor): Appointment
    {
        $this->assertOpen($appointment, 'That visit is already finished.');

        // Starting twice is a slip, not a correction. The first tap is the
        // one that tells the truth about when the clock started.
        if ($appointment->started_at === null) {
            $appointment->update([
                'status' => AppointmentStatus::InProgress,
                'started_at' => now(),
                'started_by' => $actor->id,
                'checked_in_at' => $appointment->checked_in_at ?? now(),
            ]);
        }

        return $appointment->refresh();
    }

    /**
     * Finish the cut, keep what it actually took, and settle up.
     *
     * Completing is the write that matters in this file: it is what earns the
     * barber their commission, the client their points, and the shop its
     * record of the visit.
     */
    public function finish(Appointment $appointment): Appointment
    {
        if ($appointment->started_at === null) {
            throw new VisitActionException('Start the cut before finishing it.');
        }

        if ($appointment->status === AppointmentStatus::Completed) {
            return $appointment;
        }

        DB::transaction(function () use ($appointment): void {
            $finishedAt = now();

            $appointment->update([
                'status' => AppointmentStatus::Completed,
                'completed_at' => $finishedAt,
                // Frozen here rather than derived later, so correcting a
                // forgotten tap cannot quietly rewrite the record.
                'actual_minutes' => max(
                    1,
                    (int) $appointment->started_at->diffInMinutes($finishedAt),
                ),
            ]);

            $appointment->refresh();

            $this->recordVisit($appointment);
            $this->loyalty->awardForVisit($appointment);

            // A finished visit is when we learn how often this client
            // actually comes, and it resets the clock on chasing them.
            if ($appointment->client !== null) {
                $this->reminders->recomputeCycle($appointment->client);
                $this->reminders->cancelFor($appointment->client_id, 'Client came in');
            }
        });

        return $appointment->refresh();
    }

    /**
     * May this barber take this booking onto themselves?
     *
     * The narrow case the shop actually has: the receptionist is out, the
     * client is waiting, and the barber standing there is not the one the
     * booking was auto-assigned to. Claiming is only ever onto yourself, so
     * it needs no power to move other people's work, and the trail records
     * that the barber did it rather than the desk.
     */
    public function mayClaim(Appointment $appointment, User $barber): bool
    {
        if ($appointment->staff_id === $barber->id) {
            return false;
        }

        if ($appointment->started_at !== null || ! $this->isOpen($appointment)) {
            return false;
        }

        if (! $barber->can('appointment.checkin')) {
            return false;
        }

        return $barber->branches()->whereKey($appointment->branch_id)->exists();
    }

    /**
     * The client's own history. Kept here rather than on the controller
     * because it was previously only written from the desk, which made a
     * client's visit count depend on which screen finished their cut.
     */
    private function recordVisit(Appointment $appointment): void
    {
        $profile = ClientProfile::query()
            ->where('user_id', $appointment->client_id)
            ->first();

        if ($profile === null) {
            return;
        }

        $profile->update([
            'first_visit_at' => $profile->first_visit_at ?? now(),
            'last_visit_at' => now(),
            'visit_count' => $profile->visit_count + 1,
            'lifetime_value_cents' => $profile->lifetime_value_cents + $appointment->total_cents,
        ]);
    }

    /**
     * A barber cannot be in two chairs at once, so the booking being moved
     * has to fit in the gap the new barber actually has.
     */
    private function assertFree(Appointment $appointment, User $barber): void
    {
        $clash = Appointment::query()
            ->where('staff_id', $barber->id)
            ->whereKeyNot($appointment->id)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->where('scheduled_start_at', '<', $appointment->scheduled_end_at)
            ->where('scheduled_end_at', '>', $appointment->scheduled_start_at)
            ->exists();

        if ($clash) {
            throw new VisitActionException($barber->name.' already has someone at that time.');
        }
    }

    private function assertOpen(Appointment $appointment, string $message): void
    {
        if (! $this->isOpen($appointment)) {
            throw new VisitActionException($message);
        }
    }

    private function isOpen(Appointment $appointment): bool
    {
        return ! in_array($appointment->status, [
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ], true);
    }
}
