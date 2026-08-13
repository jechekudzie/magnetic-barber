<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\LoyaltyLedger;
use App\Models\Plan;
use App\Models\Review;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Style;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on the owner's landing screen.
 *
 * Everything here is measured from bookings, which is all the system knows
 * until the till lands. "Booked value" is what was quoted, not what was taken,
 * and it is labelled that way on screen so nobody reads it as revenue.
 */
final class AdminMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function forBranch(?Branch $branch): array
    {
        $timezone = $branch?->timezone ?: config('app.timezone');
        $today = Carbon::now($timezone);

        return [
            'catalog' => $this->catalogCounts($branch),
            'today' => $this->window($branch, $today->copy()->startOfDay(), $today->copy()->endOfDay(), $timezone),
            'week' => $this->window($branch, $today->copy()->startOfWeek(), $today->copy()->endOfWeek(), $timezone),
            'upcoming' => $this->upcomingCount($branch),
            'clients' => $this->clientStats(),
            'loyalty' => [
                'outstanding' => (int) LoyaltyLedger::query()->sum('points'),
                'issued' => (int) LoyaltyLedger::query()->where('points', '>', 0)->sum('points'),
            ],
            'charts' => [
                'bookings_by_day' => $this->bookingsByDay($branch, $timezone),
                'top_services' => $this->topServices($branch),
                'by_status' => $this->byStatus($branch),
                'channel' => $this->byType($branch),
            ],
            'reviews' => [
                'published' => Review::query()->published()->count(),
                'pending' => Review::query()->where('is_public', false)->whereNull('flagged_at')->count(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function catalogCounts(?Branch $branch): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->count(),
            'services' => $branch === null
                ? Service::query()->where('is_active', true)->count()
                : $branch->services()->wherePivot('is_active', true)->count(),
            'styles' => Style::query()->where('is_active', true)->count(),
            'plans' => Plan::query()->where('is_active', true)->count(),
            'team' => StaffProfile::query()
                ->when($branch !== null, fn ($query) => $query->whereHas(
                    'user',
                    fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereKey($branch->id))
                ))
                ->count(),
            'unpriced_services' => $branch === null
                ? 0
                : Service::query()
                    ->where('is_active', true)
                    ->whereDoesntHave('branches', fn ($q) => $q->whereKey($branch->id))
                    ->count(),
        ];
    }

    /**
     * @return array{bookings: int, completed: int, value: array<string, mixed>}
     */
    private function window(?Branch $branch, Carbon $from, Carbon $to, string $timezone): array
    {
        $inWindow = fn () => $this->scoped($branch)
            ->whereBetween('scheduled_start_at', [$from->copy()->utc(), $to->copy()->utc()]);

        $live = $inWindow()->whereNotIn('status', [
            AppointmentStatus::Cancelled->value,
            AppointmentStatus::NoShow->value,
        ]);

        return [
            'bookings' => (clone $live)->count(),
            'completed' => $inWindow()->where('status', AppointmentStatus::Completed->value)->count(),
            'value' => Money::ofCents(
                (int) (clone $live)->sum('total_cents'),
                config('magnetic.default_currency'),
            )->toArray(),
        ];
    }

    private function upcomingCount(?Branch $branch): int
    {
        return $this->scoped($branch)
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('scheduled_start_at', '>=', now())
            ->count();
    }

    /**
     * @return array{total: int, returning: int, repeat_rate: float}
     */
    private function clientStats(): array
    {
        $total = ClientProfile::query()->count();
        $returning = ClientProfile::query()->where('visit_count', '>', 1)->count();

        return [
            'total' => $total,
            'returning' => $returning,
            // The number the deck puts on the owner's home screen.
            'repeat_rate' => $total > 0 ? round(($returning / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Fourteen days of booking counts, for the trend bar chart.
     *
     * @return array<int, array{label: string, date: string, count: int, value: int}>
     */
    private function bookingsByDay(?Branch $branch, string $timezone): array
    {
        $start = Carbon::now($timezone)->startOfDay()->subDays(6);

        $appointments = $this->scoped($branch)
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->whereBetween('scheduled_start_at', [
                $start->copy()->utc(),
                $start->copy()->addDays(14)->endOfDay()->utc(),
            ])
            ->get(['scheduled_start_at', 'total_cents']);

        // Bucketed once by local date, rather than rescanning the window for
        // each of the fourteen days.
        $buckets = [];

        foreach ($appointments as $appointment) {
            $date = $appointment->scheduled_start_at?->copy()->timezone($timezone)->toDateString();

            if ($date === null) {
                continue;
            }

            $buckets[$date] ??= ['count' => 0, 'value' => 0];
            $buckets[$date]['count']++;
            $buckets[$date]['value'] += $appointment->total_cents;
        }

        $days = [];

        for ($offset = 0; $offset < 14; $offset++) {
            $day = $start->copy()->addDays($offset);
            $date = $day->toDateString();

            $days[] = [
                'label' => $day->format('D j'),
                'date' => $date,
                'count' => $buckets[$date]['count'] ?? 0,
                'value' => $buckets[$date]['value'] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array{name: string, count: int, value: int}>
     */
    private function topServices(?Branch $branch): array
    {
        // The query builder rather than Eloquent: this is an aggregate, and
        // hydrating models for counted rows would be wasted work.
        return DB::table('appointment_services')
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->when(
                $branch !== null,
                fn ($query) => $query->where('appointments.branch_id', $branch->id)
            )
            ->whereNotIn('appointments.status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->groupBy('appointment_services.name_snapshot')
            ->orderByDesc('booked')
            ->limit(6)
            ->get([
                'appointment_services.name_snapshot as name',
                DB::raw('count(*) as booked'),
                DB::raw('sum(appointment_services.price_cents) as value'),
            ])
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'count' => (int) $row->booked,
                'value' => (int) $row->value,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function byStatus(?Branch $branch): array
    {
        $counts = $this->scoped($branch)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AppointmentStatus::cases())
            ->map(fn (AppointmentStatus $status): array => [
                'label' => $status->label(),
                'value' => (int) ($counts[$status->value] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * Shop versus house call, the split the deck asks the owner to watch.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function byType(?Branch $branch): array
    {
        $counts = $this->scoped($branch)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return collect([
            'scheduled' => 'At the shop',
            'house_call' => 'House call',
            'walkin' => 'Walk in',
        ])
            ->map(fn (string $label, string $type): array => [
                'label' => $label,
                'value' => (int) ($counts[$type] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return Builder<Appointment>
     */
    private function scoped(?Branch $branch): Builder
    {
        return Appointment::query()
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id));
    }
}
