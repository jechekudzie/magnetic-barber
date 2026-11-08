<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\ReminderService;
use App\Services\VisitService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * The diary.
 *
 * The default is a day grid with a column per chair, because that is the
 * question a shop actually asks: who is with whom, and where are the gaps.
 * A flat list answers neither without scrolling.
 */
class BookingController extends AdminController
{
    /** Rows in the list view before it admits it is truncating. */
    private const MAX_ROWS = 200;

    /** The day grid draws a line every half hour. */
    private const GRID_STEP = 30;

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'view' => ['nullable', 'string', 'in:day,week,list'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // An owner can watch every branch at once; anyone else sees the one
        // they are standing in.
        $reachable = $this->reachableBranches($request);
        $scope = $validated['scope'] ?? null;

        if ($scope === null) {
            $current = $this->currentBranch($request);
            $scope = $current === null ? 'all' : $current->slug;
        }

        $branches = $scope === 'all'
            ? $reachable
            : $reachable->where('slug', $scope)->values();

        if ($branches->isEmpty()) {
            $branches = $reachable;
            $scope = 'all';
        }

        $timezone = $branches->first()?->timezone ?: config('app.timezone');
        $view = $validated['view'] ?? 'day';
        $anchor = Carbon::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);

        [$from, $to] = match ($view) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            default => [
                Carbon::parse($validated['from'] ?? $anchor->toDateString(), $timezone)->startOfDay(),
                Carbon::parse($validated['to'] ?? $anchor->copy()->addDays(13)->toDateString(), $timezone)->endOfDay(),
            ],
        };

        $query = $this->scoped($branches->pluck('id')->all(), $from, $to, $validated);

        $matching = (clone $query)->count();

        $appointments = $query
            ->with(['branch:id,name,slug', 'client.clientProfile', 'staff.staffProfile', 'services', 'houseCall'])
            ->orderBy('scheduled_start_at')
            ->limit($view === 'list' ? self::MAX_ROWS : 500)
            ->get();

        // A barber may see who is in their chair but not how to contact them.
        $maySeeContact = $request->user()?->can('client.contact.view') ?? false;

        $bookings = $appointments
            ->map(fn (Appointment $appointment): array => $this->appointmentRow($appointment, $timezone, $maySeeContact))
            ->all();

        return inertia('admin/bookings', [
            'branchContext' => $this->branchContext($request),
            'view' => $view,
            'date' => $anchor->toDateString(),
            'scope' => $scope,
            'scopes' => $reachable
                ->map(fn (Branch $branch): array => [
                    'value' => $branch->slug,
                    'label' => $branch->name,
                ])
                ->prepend(['value' => 'all', 'label' => 'All branches'])
                ->values()
                ->all(),
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->rangeLabel($view, $from, $to),
                'previous' => $this->step($anchor, $view, -1),
                'next' => $this->step($anchor, $view, 1),
                'today' => now($timezone)->toDateString(),
            ],
            'filters' => [
                'status' => $validated['status'] ?? 'all',
                'search' => $validated['search'] ?? '',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'statuses' => collect(AppointmentStatus::cases())
                ->map(fn (AppointmentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            // One grid per branch in scope, so "all branches" stacks rather
            // than mixing two shops' chairs into one column set.
            'grids' => $branches
                ->map(fn (Branch $branch): array => $this->grid($branch, $anchor))
                ->all(),
            'days' => $this->weekDays($from, $to, $view),
            'bookings' => $bookings,
            'summary' => [
                'total' => $matching,
                'shown' => count($bookings),
                'truncated' => $matching > count($bookings),
                'confirmed' => $appointments->whereIn('status', [
                    AppointmentStatus::Pending,
                    AppointmentStatus::Confirmed,
                ])->count(),
                'completed' => $appointments->where('status', AppointmentStatus::Completed)->count(),
                'cancelled' => $appointments->whereIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])->count(),
                'value' => Money::ofCents(
                    (int) $appointments
                        ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
                        ->sum('total_cents'),
                    config('magnetic.default_currency'),
                )->toArray(),
            ],
        ]);
    }

    /**
     * Moving a booking through its states. Completing it is what earns the
     * client their points, so that write is the one that matters here.
     */
    public function updateStatus(
        Request $request,
        Appointment $appointment,
        LoyaltyService $loyalty,
        ReminderService $reminders,
    ): RedirectResponse {
        abort_unless($request->user()?->can('appointment.update'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,checked_in,in_progress,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $status = AppointmentStatus::from($validated['status']);

        DB::transaction(function () use ($appointment, $status, $validated, $request, $loyalty, $reminders): void {
            $appointment->update([
                'status' => $status,
                'checked_in_at' => $status === AppointmentStatus::CheckedIn ? now() : $appointment->checked_in_at,
                'started_at' => $status === AppointmentStatus::InProgress ? now() : $appointment->started_at,
                'completed_at' => $status === AppointmentStatus::Completed ? now() : $appointment->completed_at,
                'cancelled_at' => in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
                    ? now()
                    : null,
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_by' => in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
                    ? $request->user()->id
                    : null,
            ]);

            if ($status === AppointmentStatus::Completed) {
                $this->settle($appointment->refresh(), $loyalty, $reminders);
            }
        });

        return back()->with('success', "{$appointment->reference} is now {$status->label()}.");
    }

    /**
     * Start the cut, from the desk machine.
     *
     * Its own action rather than a status change, and literally the same code
     * the barber's phone runs, so the clock cannot mean two different things
     * depending on which screen started it.
     */
    public function start(Request $request, Appointment $appointment, VisitService $visits): RedirectResponse
    {
        $this->assertReachable($request, $appointment);

        $visits->start($appointment, $request->user());

        return back()->with('success', "{$appointment->reference} is in the chair.");
    }

    /**
     * Finish the cut, and keep what it actually took.
     */
    public function finish(Request $request, Appointment $appointment, VisitService $visits): RedirectResponse
    {
        $this->assertReachable($request, $appointment);

        $appointment = $visits->finish($appointment);

        return back()->with(
            'success',
            "{$appointment->reference} done in {$appointment->actual_minutes} minutes.",
        );
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  array<string, mixed>  $validated
     * @return Builder<Appointment>
     */
    private function scoped(array $branchIds, Carbon $from, Carbon $to, array $validated): Builder
    {
        return Appointment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('scheduled_start_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->when(
                ! empty($validated['status']) && $validated['status'] !== 'all',
                fn ($query) => $query->where('status', $validated['status'])
            )
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $term = $validated['search'];
                $phone = Phone::normalise($term);

                $query->where(function ($q) use ($term, $phone) {
                    $q->where('reference', 'like', "%{$term}%")
                        ->orWhereHas('client', fn ($c) => $c
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', $phone));
                });
            });
    }

    /**
     * The shape of one branch's day grid: how tall it is, and its chairs.
     *
     * @return array<string, mixed>
     */
    private function grid(Branch $branch, Carbon $anchor): array
    {
        $columns = $branch->staff()
            ->where('users.is_active', true)
            ->whereHas('staffProfile', fn ($query) => $query->where('is_bookable', true))
            ->with('staffProfile')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->ulid,
                'name' => $member->staffProfile?->name() ?? $member->name,
                'title' => $member->staffProfile?->title,
            ])
            ->values()
            ->all();

        return [
            'branch' => ['slug' => $branch->slug, 'name' => $branch->name],
            'opens_minutes' => $this->toMinutes($branch->opens_at),
            'closes_minutes' => $this->toMinutes($branch->closes_at),
            'step' => self::GRID_STEP,
            'open_today' => $branch->isOpenOn((int) $anchor->dayOfWeek),
            'columns' => $columns,
        ];
    }

    /**
     * The seven columns of a week view.
     *
     * @return array<int, array{date: string, label: string, weekday: string, is_today: bool}>
     */
    private function weekDays(Carbon $from, Carbon $to, string $view): array
    {
        if ($view !== 'week') {
            return [];
        }

        $days = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('j M'),
                'weekday' => $cursor->format('D'),
                'is_today' => $cursor->isToday(),
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    private function step(Carbon $anchor, string $view, int $direction): string
    {
        return match ($view) {
            'week' => $anchor->copy()->addWeeks($direction)->toDateString(),
            default => $anchor->copy()->addDays($direction)->toDateString(),
        };
    }

    private function rangeLabel(string $view, Carbon $from, Carbon $to): string
    {
        return match ($view) {
            'day' => $from->format('l j F Y'),
            default => $from->format('j M').' to '.$to->format('j M Y'),
        };
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hour) * 60 + ((int) $minute);
    }

    /**
     * A completed visit is what makes someone a returning client, so the
     * counters that drive retention are updated here rather than on booking.
     */
    /**
     * Everything a completed visit owes: the client's history, their points,
     * and calling off the chase. Reached from the status dropdown, which can
     * complete a visit nobody ever tapped Start on; finish() goes through
     * VisitService instead, which does this and the clock.
     */
    private function settle(
        Appointment $appointment,
        LoyaltyService $loyalty,
        ReminderService $reminders,
    ): void {
        $profile = ClientProfile::query()
            ->where('user_id', $appointment->client_id)
            ->first();

        $profile?->update([
            'first_visit_at' => $profile->first_visit_at ?? now(),
            'last_visit_at' => now(),
            'visit_count' => $profile->visit_count + 1,
            'lifetime_value_cents' => $profile->lifetime_value_cents + $appointment->total_cents,
        ]);

        $loyalty->awardForVisit($appointment);

        if ($appointment->client !== null) {
            $reminders->recomputeCycle($appointment->client);
            $reminders->cancelFor($appointment->client_id, 'Client came in');
        }
    }
}
