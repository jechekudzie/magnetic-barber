<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Services\BookingService;
use App\Services\LoyaltyService;
use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The day's chairs, for whoever is working them.
 *
 * What a staff member sees is decided by permission, not by job title:
 * appointment.view.branch shows the whole shop, appointment.view.own shows
 * only their own column. A barber holds the second and not the first, which
 * is what keeps one barber out of another's book.
 */
class DiaryController extends Controller
{
    public function index(Request $request, BookingService $booking): JsonResponse
    {
        $user = $request->user();
        $branch = $this->branchFor($request);

        abort_unless(
            $user->can('appointment.view.branch') || $user->can('appointment.view.own'),
            403,
        );

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = $branch->timezone ?: config('magnetic.timezone');

        $day = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $timezone)
            : CarbonImmutable::now($timezone);

        $appointments = Appointment::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('scheduled_start_at', [
                $day->startOfDay()->utc(),
                $day->endOfDay()->utc(),
            ])
            // A barber who may only see their own column gets exactly that.
            ->when(
                ! $user->can('appointment.view.branch'),
                fn ($query) => $query->where('staff_id', $user->id),
            )
            ->with(['branch', 'staff.staffProfile', 'services', 'houseCall', 'client.clientProfile'])
            ->orderBy('scheduled_start_at')
            ->get();

        return response()->json([
            'date' => $day->toDateString(),
            'branch' => ['slug' => $branch->slug, 'name' => $branch->name],
            'counts' => [
                'total' => $appointments->count(),
                'confirmed' => $appointments->where('status', AppointmentStatus::Confirmed)->count(),
                'in_progress' => $appointments->where('status', AppointmentStatus::InProgress)->count(),
                'completed' => $appointments->where('status', AppointmentStatus::Completed)->count(),
                'cancelled' => $appointments->whereIn('status', [
                    AppointmentStatus::Cancelled, AppointmentStatus::NoShow,
                ])->count(),
            ],
            'data' => AppointmentResource::collection($appointments)->resolve(),
        ]);
    }

    /**
     * Move a booking along: checked in, in the chair, done, no show.
     *
     * This is the till of the system. Completing a visit is what records the
     * client's visit, awards their points and earns the barber their
     * commission, so it runs in one transaction.
     */
    public function updateStatus(
        Request $request,
        Appointment $appointment,
        LoyaltyService $loyalty,
        ReminderService $reminders,
    ): JsonResponse {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,checked_in,in_progress,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $status = AppointmentStatus::from($validated['status']);

        abort_unless($this->mayMoveTo($request, $appointment, $status), 403);

        DB::transaction(function () use ($appointment, $status, $validated, $user, $loyalty, $reminders): void {
            $cancelling = in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true);

            $appointment->update([
                'status' => $status,
                'checked_in_at' => $status === AppointmentStatus::CheckedIn ? now() : $appointment->checked_in_at,
                'started_at' => $status === AppointmentStatus::InProgress ? now() : $appointment->started_at,
                'completed_at' => $status === AppointmentStatus::Completed ? now() : $appointment->completed_at,
                'cancelled_at' => $cancelling ? now() : null,
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_by' => $cancelling ? $user->id : null,
            ]);

            if ($status === AppointmentStatus::Completed) {
                $appointment->refresh();
                $loyalty->awardForVisit($appointment);

                if ($appointment->client !== null) {
                    $reminders->recomputeCycle($appointment->client);
                    $reminders->cancelFor($appointment->client_id, 'Client came in');
                }
            }
        });

        return response()->json([
            'data' => new AppointmentResource(
                $appointment->refresh()->load(['branch', 'staff.staffProfile', 'services'])
            ),
        ]);
    }

    /**
     * One job, in full.
     *
     * What a barber opens when they tap a card: who is coming, what they are
     * having, what has been paid, and the clock. Separate from the diary
     * listing because this is the screen somebody works from while the client
     * is in the chair, and it has to stay current on its own.
     */
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($this->mayWork($request, $appointment), 403);

        return $this->respondWith($appointment);
    }

    /**
     * Leave a note on the job while it is happening.
     *
     * Staff notes, not the client's. A barber writing "left side sits lower,
     * took it shorter" is the sort of thing that makes the next visit good,
     * and it has to be writable from the chair rather than remembered.
     */
    public function note(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($this->mayWork($request, $appointment), 403);
        abort_unless($request->user()->can('client.note.write'), 403);

        $validated = $request->validate([
            'staff_note' => ['required', 'string', 'max:1000'],
        ]);

        $appointment->update(['staff_note' => $validated['staff_note']]);

        return $this->respondWith($appointment->refresh());
    }

    /**
     * Start the cut.
     *
     * Its own endpoint rather than a status change, because for a barber this
     * is one tap on a card and not a menu of states. It also starts the clock
     * that makes the chair genuinely unavailable: a cut that runs past its
     * slot keeps the barber busy in the booking grid, so nobody else is sold
     * a time that is still warm.
     */
    public function start(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($this->mayWork($request, $appointment), 403);

        abort_if(
            in_array($appointment->status, [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ], true),
            422,
            'That visit is already finished.',
        );

        // Starting twice is a slip, not a correction. The first tap is the
        // one that tells the truth about when the clock started.
        if ($appointment->started_at === null) {
            $appointment->update([
                'status' => AppointmentStatus::InProgress,
                'started_at' => now(),
                'checked_in_at' => $appointment->checked_in_at ?? now(),
            ]);
        }

        return $this->respondWith($appointment->refresh());
    }

    /**
     * Finish the cut, and keep what it actually took.
     */
    public function finish(
        Request $request,
        Appointment $appointment,
        LoyaltyService $loyalty,
        ReminderService $reminders,
    ): JsonResponse {
        abort_unless($this->mayWork($request, $appointment), 403);
        abort_unless($request->user()->can('appointment.complete'), 403);

        abort_if(
            $appointment->started_at === null,
            422,
            'Start the cut before finishing it.',
        );

        if ($appointment->status !== AppointmentStatus::Completed) {
            DB::transaction(function () use ($appointment, $loyalty, $reminders): void {
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
                $loyalty->awardForVisit($appointment);

                if ($appointment->client !== null) {
                    $reminders->recomputeCycle($appointment->client);
                    $reminders->cancelFor($appointment->client_id, 'Client came in');
                }
            });
        }

        return $this->respondWith($appointment->refresh());
    }

    private function respondWith(Appointment $appointment): JsonResponse
    {
        return response()->json([
            'data' => new AppointmentResource(
                $appointment->load(['branch', 'staff.staffProfile', 'services', 'payments', 'client.clientProfile'])
            ),
        ]);
    }

    /**
     * A barber may work their own column; anyone who can see the branch diary
     * may work any of it.
     */
    private function mayWork(Request $request, Appointment $appointment): bool
    {
        $user = $request->user();

        return $user->can('appointment.view.branch') || $appointment->staff_id === $user->id;
    }

    /**
     * Each move is its own permission, and a barber may only move their own
     * bookings. The permissions already exist; this is where the API honours
     * them.
     */
    private function mayMoveTo(Request $request, Appointment $appointment, AppointmentStatus $status): bool
    {
        $user = $request->user();

        if (! $user->can('appointment.view.branch') && $appointment->staff_id !== $user->id) {
            return false;
        }

        return match ($status) {
            AppointmentStatus::CheckedIn => $user->can('appointment.checkin'),
            AppointmentStatus::Completed => $user->can('appointment.complete'),
            AppointmentStatus::NoShow => $user->can('appointment.no-show'),
            AppointmentStatus::Cancelled => $user->can('appointment.cancel'),
            default => $user->can('appointment.update'),
        };
    }

    /**
     * The branch this request is acting in, put there by SetPermissionsBranch
     * from the X-Branch-Id header or the user's primary branch.
     */
    private function branchFor(Request $request): Branch
    {
        abort_unless(app()->bound('currentBranch'), 403, 'You are not assigned to a branch.');

        return app('currentBranch');
    }
}
