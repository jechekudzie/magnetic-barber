<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\CommissionService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * What the floor earned, and how long it took.
 *
 * The owner can already see today in the app. This is the desktop version,
 * because payroll is not done on a phone: it covers a range rather than a
 * day, and it puts the time a cut actually took beside the money, which is
 * the pair that tells you whether the price list and the diary agree with
 * reality.
 */
class EarningsController extends AdminController
{
    /** A range longer than this is a report, not a screen. */
    private const MAX_DAYS = 92;

    public function index(Request $request, CommissionService $commission): Response
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 403, 'You are not assigned to a branch.');

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $timezone = $branch->timezone ?: config('magnetic.timezone');
        $today = CarbonImmutable::now($timezone);

        $to = isset($validated['to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['to'], $timezone)
            : $today;

        $from = isset($validated['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['from'], $timezone)
            : $to->subDays(6);

        if ($from->isAfter($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS);
        }

        $fromUtc = $from->startOfDay()->utc();
        $toUtc = $to->endOfDay()->utc();

        $currency = config('magnetic.reporting_currency');

        /*
         * One query for the whole range, grouped per barber. The per day
         * breakdown comes off the same rows rather than a query per day,
         * which at 92 days would be ninety two round trips for one screen.
         */
        $rows = DB::table('appointment_services')
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->join('users', 'users.id', '=', 'appointment_services.staff_id')
            ->where('appointments.branch_id', $branch->id)
            ->where('appointments.status', AppointmentStatus::Completed->value)
            ->whereNotNull('appointments.completed_at')
            ->whereBetween('appointments.completed_at', [$fromUtc, $toUtc])
            ->select([
                'users.ulid as staff_ulid',
                'users.name as staff_name',
                'appointment_services.commission_cents',
                'appointment_services.commission_rate',
                'appointment_services.price_cents',
                'appointment_services.qty',
                'appointments.id as appointment_id',
                'appointments.client_id',
                'appointments.completed_at',
            ])
            ->get();

        $byStaff = $rows
            ->groupBy('staff_ulid')
            ->map(function ($lines) use ($currency): array {
                $first = $lines->first();

                return [
                    'staff_id' => (string) $first->staff_ulid,
                    'name' => (string) $first->staff_name,
                    'rate' => (float) ($first->commission_rate ?? 0),
                    'services' => $lines->count(),
                    'clients' => $lines->pluck('client_id')->unique()->count(),
                    'visits' => $lines->pluck('appointment_id')->unique()->count(),
                    'earned' => Money::ofCents((int) $lines->sum('commission_cents'), $currency)->toArray(),
                    'took' => Money::ofCents(
                        (int) $lines->sum(fn ($line): int => (int) $line->price_cents * (int) $line->qty),
                        $currency,
                    )->toArray(),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['earned']['cents'])
            ->values()
            ->all();

        /*
         * How long cuts actually took against what the catalog allowed.
         * A service booked at 45 minutes that reliably runs to 60 is costing
         * the shop a slot a day, and nothing else on any screen says so.
         */
        /*
         * How long cuts actually took against what the catalog allowed.
         * A service booked at 45 minutes that reliably runs to 60 is costing
         * the shop a slot a day, and nothing else on any screen says so.
         *
         * Built with a loop rather than a chain of collection calls: the
         * nesting is one visit to many lines, and the chain that expresses
         * that reads worse than the thing it replaces.
         *
         * @var array<string, array{name: string, cuts: int, booked: int, actual: int}> $tallies
         */
        $tallies = [];

        $timed = Appointment::query()
            ->where('branch_id', $branch->id)
            ->where('status', AppointmentStatus::Completed)
            ->whereNotNull('actual_minutes')
            ->whereBetween('completed_at', [$fromUtc, $toUtc])
            ->with('services')
            ->get();

        foreach ($timed as $appointment) {
            foreach ($appointment->services as $line) {
                $name = (string) $line->name_snapshot;

                $tallies[$name] ??= ['name' => $name, 'cuts' => 0, 'booked' => 0, 'actual' => 0];

                $tallies[$name]['cuts']++;
                $tallies[$name]['booked'] += (int) $line->duration_minutes;
                // One cut can hold several lines; the clock covers the visit,
                // so the visit is attributed to each line it contained.
                $tallies[$name]['actual'] += (int) $appointment->actual_minutes;
            }
        }

        $timing = array_values(array_map(
            fn (array $row): array => [
                'name' => $row['name'],
                'cuts' => $row['cuts'],
                'booked' => (int) round($row['booked'] / max(1, $row['cuts'])),
                'actual' => (int) round($row['actual'] / max(1, $row['cuts'])),
            ],
            $tallies,
        ));

        usort($timing, fn (array $a, array $b): int => $b['cuts'] <=> $a['cuts']);

        return inertia('admin/earnings', [
            'branchContext' => $this->branchContext($request),
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
            ],
            'totals' => [
                'earned' => Money::ofCents((int) $rows->sum('commission_cents'), $currency)->toArray(),
                'took' => Money::ofCents(
                    (int) $rows->sum(fn ($line): int => (int) $line->price_cents * (int) $line->qty),
                    $currency,
                )->toArray(),
                'visits' => $rows->pluck('appointment_id')->unique()->count(),
                'services' => $rows->count(),
            ],
            'staff' => $byStaff,
            'timing' => $timing,
            'today' => $commission->dailyEarnings($branch),
        ]);
    }
}
