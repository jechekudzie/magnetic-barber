<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The hardest read in the system. Get it right once and the website, the
 * WhatsApp flow and the mobile app all just consume it.
 *
 * Times are computed in the branch's own timezone and returned as UTC ISO
 * strings alongside a local label, so no client has to do timezone maths.
 */
final class AvailabilityService
{
    /** Slots start on a 15 minute grid. */
    private const GRID_MINUTES = 15;

    /** How far ahead of now the first bookable slot may be. */
    private const LEAD_MINUTES = 30;

    /**
     * @param  list<int>  $serviceIds
     * @return array{
     *     date: string,
     *     duration_minutes: int,
     *     staff: array<int, array{id: string|null, name: string, slots: array<int, array{start: string, label: string}>}>,
     *     any_staff: array<int, array{start: string, label: string}>,
     *     closed: bool,
     *     provisional: bool,
     *     reason: string|null
     * }
     */
    public function forDate(
        Branch $branch,
        CarbonInterface $date,
        array $serviceIds,
        ?int $staffId = null,
        bool $houseCall = false,
    ): array {
        $timezone = $branch->timezone ?: config('app.timezone');

        // The app runs on CarbonImmutable, so accept any Carbon and normalise
        // to the mutable one the slot maths below is written against.
        $localDate = Carbon::instance($date->toDateTime())
            ->timezone($timezone)
            ->startOfDay();

        $duration = $this->requiredMinutes($branch, $serviceIds);

        $empty = [
            'date' => $localDate->toDateString(),
            'duration_minutes' => $duration,
            'staff' => [],
            'any_staff' => [],
            'provisional' => false,
        ];

        $tradesToday = $houseCall
            ? $branch->isOpenForHouseCallsOn((int) $localDate->dayOfWeek)
            : $branch->isOpenOn((int) $localDate->dayOfWeek);

        if (! $tradesToday) {
            return [...$empty, 'closed' => true, 'reason' => $houseCall
                ? 'We do not take house calls that day.'
                : 'The shop is closed that day.'];
        }

        if ($localDate->isPast() && ! $localDate->isToday()) {
            return [...$empty, 'closed' => true, 'reason' => 'That date has passed.'];
        }

        // The calendar is shown before services are chosen, so with nothing
        // selected we fall back to the shortest thing the branch sells. The
        // grid is marked provisional and re-checked once the real services are
        // known, which is what stops a 20 minute slot holding a 90 minute job.
        $provisional = $duration === 0;

        if ($provisional) {
            $duration = $this->shortestBlockFor($branch);
            $empty['duration_minutes'] = $duration;
        }

        if ($duration === 0) {
            return [...$empty, 'closed' => true, 'provisional' => true, 'reason' => 'Nothing is bookable at this branch yet.'];
        }

        $staff = $this->bookableStaff($branch, $serviceIds, $staffId, $houseCall);

        if ($staff->isEmpty()) {
            return [...$empty, 'closed' => true, 'reason' => $houseCall
                ? 'No barber is free to travel that day.'
                : 'No barber is available that day.'];
        }

        $booked = $this->bookedIntervals($branch, $localDate, $timezone);

        $perStaff = $staff
            ->map(fn (User $member): array => [
                'id' => $member->ulid,
                'name' => $member->staffProfile?->name() ?? $member->name,
                'slots' => $this->slotsFor($branch, $member, $localDate, $duration, $timezone, $booked, $houseCall),
            ])
            ->filter(fn (array $row): bool => $row['slots'] !== [])
            ->values()
            ->all();

        return [
            ...$empty,
            'duration_minutes' => $duration,
            'provisional' => $provisional,
            'staff' => $perStaff,
            'any_staff' => $this->mergeSlots($perStaff),
            'closed' => $perStaff === [],
            'reason' => $perStaff === [] ? $this->emptyReason($localDate, $timezone) : null,
        ];
    }

    /**
     * Sum of every chosen service's duration at this branch, plus its buffer.
     *
     * @param  list<int>  $serviceIds
     */
    public function requiredMinutes(Branch $branch, array $serviceIds): int
    {
        if ($serviceIds === []) {
            return 0;
        }

        return (int) $branch->services()
            ->whereIn('services.id', $serviceIds)
            ->wherePivot('is_active', true)
            ->get()
            ->sum(fn (Service $service): int => $service->durationForLoadedBranch() + $service->buffer_minutes);
    }

    /**
     * Why a day came back with nothing. "Fully booked" is wrong when the shop
     * has simply closed for the evening, and that reads as a broken picker.
     */
    private function emptyReason(Carbon $localDate, string $timezone): string
    {
        if (! $localDate->isToday()) {
            return 'Fully booked that day. Try another.';
        }

        $now = Carbon::now($timezone);
        $closingMinutes = $this->toMinutes($localDate->copy()->format('H:i'));

        return $now->hour * 60 + $now->minute >= $closingMinutes
            ? 'Today is finished. Try tomorrow.'
            : 'Nothing left today. Try tomorrow.';
    }

    /**
     * The first day in the next fortnight that actually has a free slot.
     *
     * The wizard opens on this rather than on today, because today is empty
     * every evening after closing and an empty picker looks broken.
     *
     * @param  list<int>  $serviceIds
     */
    public function firstBookableDate(Branch $branch, array $serviceIds = [], bool $houseCall = false): ?string
    {
        $timezone = $branch->timezone ?: config('app.timezone');
        $cursor = Carbon::now($timezone)->startOfDay();

        for ($offset = 0; $offset < 14; $offset++) {
            $day = $cursor->copy()->addDays($offset);

            if (! $branch->isOpenOn((int) $day->dayOfWeek)) {
                continue;
            }

            $result = $this->forDate($branch, $day, $serviceIds, null, $houseCall);

            if ($result['any_staff'] !== []) {
                return $result['date'];
            }
        }

        return null;
    }

    /**
     * The shortest bookable block at this branch, used to draw the calendar
     * before anyone has chosen a service.
     */
    public function shortestBlockFor(Branch $branch): int
    {
        $shortest = $branch->services()
            ->wherePivot('is_active', true)
            ->get()
            ->map(fn (Service $service): int => $service->durationForLoadedBranch() + $service->buffer_minutes)
            ->min();

        return (int) ($shortest ?? 0);
    }

    /**
     * @param  list<int>  $serviceIds
     * @return Collection<int, User>
     */
    public function bookableStaff(
        Branch $branch,
        array $serviceIds = [],
        ?int $staffId = null,
        bool $houseCall = false,
    ): Collection {
        return $branch->staff()
            ->where('users.is_active', true)
            ->whereHas('staffProfile', fn ($query) => $query
                ->where('is_bookable', true)
                ->when($houseCall, fn ($q) => $q->where('accepts_house_calls', true)))
            ->when($staffId !== null, fn ($query) => $query->whereKey($staffId))
            ->with('staffProfile')
            ->get();
    }

    /**
     * Every slot-holding appointment that day, as [start, end] minute offsets
     * from midnight, keyed by staff id. One query for the whole grid.
     *
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function bookedIntervals(Branch $branch, Carbon $localDate, string $timezone): array
    {
        $appointments = Appointment::query()
            ->where('branch_id', $branch->id)
            ->blocking()
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [
                $localDate->copy()->startOfDay()->utc(),
                $localDate->copy()->endOfDay()->utc(),
            ])
            ->get(['staff_id', 'scheduled_start_at', 'scheduled_end_at']);

        $intervals = [];

        foreach ($appointments as $appointment) {
            $staffId = $appointment->staff_id ?? 0;

            $start = $appointment->scheduled_start_at->copy()->timezone($timezone);
            $end = ($appointment->scheduled_end_at ?? $start)->copy()->timezone($timezone);

            $midnight = $localDate->copy()->startOfDay();

            $intervals[$staffId][] = [
                (int) $start->diffInMinutes($midnight, true),
                (int) $end->diffInMinutes($midnight, true),
            ];
        }

        return $intervals;
    }

    /**
     * @param  array<int, array<int, array{0: int, 1: int}>>  $booked
     * @return array<int, array{start: string, label: string}>
     */
    private function slotsFor(
        Branch $branch,
        User $member,
        Carbon $localDate,
        int $duration,
        string $timezone,
        array $booked,
        bool $houseCall = false,
    ): array {
        [$shiftStart, $shiftEnd] = $this->shiftMinutes($branch, $member, $localDate, $houseCall);

        if ($shiftStart === null || $shiftEnd === null) {
            return [];
        }

        $taken = $booked[$member->id] ?? [];
        $earliest = $this->earliestMinuteToday($localDate, $timezone);

        $slots = [];

        for ($minute = $shiftStart; $minute + $duration <= $shiftEnd; $minute += self::GRID_MINUTES) {
            if ($minute < $earliest) {
                continue;
            }

            if ($this->overlaps($minute, $minute + $duration, $taken)) {
                continue;
            }

            $start = $localDate->copy()->startOfDay()->addMinutes($minute);

            $slots[] = [
                'start' => $start->copy()->utc()->toIso8601String(),
                'label' => $start->format('g:ia'),
            ];
        }

        return $slots;
    }

    /**
     * A barber with no working_hours rows works the branch's opening hours.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function shiftMinutes(
        Branch $branch,
        User $member,
        Carbon $localDate,
        bool $houseCall = false,
    ): array {
        $windowStart = $this->toMinutes(
            $houseCall ? $branch->houseCallOpensAt() : $branch->opens_at
        );
        $windowEnd = $this->toMinutes(
            $houseCall ? $branch->houseCallClosesAt() : $branch->closes_at
        );

        $hours = WorkingHour::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $member->id)
            ->where('weekday', (int) $localDate->dayOfWeek)
            ->first();

        if ($hours === null) {
            return [$windowStart, $windowEnd];
        }

        // A barber's own shift never widens the branch window, only narrows it.
        return [
            max($windowStart, $this->toMinutes($hours->starts_at)),
            min($windowEnd, $this->toMinutes($hours->ends_at)),
        ];
    }

    /**
     * Nothing may be booked in the past, or inside the lead time.
     */
    private function earliestMinuteToday(Carbon $localDate, string $timezone): int
    {
        if (! $localDate->isToday()) {
            return 0;
        }

        $now = Carbon::now($timezone)->addMinutes(self::LEAD_MINUTES);

        // Round up to the next grid mark so slots stay on the quarter hour.
        $minutes = $now->hour * 60 + $now->minute;

        return (int) (ceil($minutes / self::GRID_MINUTES) * self::GRID_MINUTES);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $taken
     */
    private function overlaps(int $start, int $end, array $taken): bool
    {
        foreach ($taken as [$bookedStart, $bookedEnd]) {
            if ($start < $bookedEnd && $end > $bookedStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * The "any barber" list: every distinct time somebody is free.
     *
     * @param  array<int, array{id: string|null, name: string, slots: array<int, array{start: string, label: string}>}>  $perStaff
     * @return array<int, array{start: string, label: string}>
     */
    private function mergeSlots(array $perStaff): array
    {
        return collect($perStaff)
            ->flatMap(fn (array $row): array => $row['slots'])
            ->unique('start')
            ->sortBy('start')
            ->values()
            ->all();
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hour) * 60 + ((int) $minute);
    }
}
