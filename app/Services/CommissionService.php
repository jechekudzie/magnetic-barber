<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What each barber earns, and when.
 *
 * A barber is paid a percentage of every service line they personally
 * rendered. Attribution is already per line rather than per appointment,
 * because two barbers can work one booking: a cut from one, a facial from the
 * other. Paying on the appointment total would pay the wrong person.
 *
 * Only completed work counts. A cancelled or no show appointment has lines
 * carrying a commission figure, and they are deliberately excluded here rather
 * than zeroed on the row, so that a booking reinstated by the desk does not
 * need its money recalculated.
 */
final class CommissionService
{
    /**
     * The rate in force for this barber at this branch, as a percentage.
     *
     * Falls back to the house rate when a barber has no rate of their own,
     * which is the normal case: the per barber column exists for exceptions.
     */
    public function rateFor(User $staff, Branch $branch): float
    {
        $rate = $staff->branches()
            ->whereKey($branch->id)
            ->first()
            ?->getAttribute('pivot')
            ?->getAttribute('commission_rate');

        return (float) ($rate ?? config('magnetic.commission_rate'));
    }

    /**
     * The money owed on one line, at a given rate.
     *
     * Rounded half up to the cent. At the house rate of 5 percent a $10 cut
     * earns exactly 50c, so the rounding only bites on odd prices.
     */
    public function commissionOn(Money $linePrice, float $rate): Money
    {
        return Money::ofCents(
            (int) round($linePrice->cents * $rate / 100),
            $linePrice->currency,
        );
    }

    /**
     * Freeze the rate and the money onto a line.
     *
     * Called when the line is created, and again if the line is moved to a
     * different barber. Both the rate and the figure are written, so a payslip
     * can show its own arithmetic.
     */
    public function attribute(AppointmentService $line, Branch $branch): void
    {
        $staff = $line->staff;

        if ($staff === null) {
            $line->forceFill(['commission_rate' => null, 'commission_cents' => 0])->save();

            return;
        }

        $rate = $this->rateFor($staff, $branch);

        $line->forceFill([
            'commission_rate' => $rate,
            'commission_cents' => $this->commissionOn($line->price(), $rate)->cents,
        ])->save();
    }

    /**
     * The shop's own day, as a pair of UTC bounds.
     *
     * Read in the branch's timezone, because a shop trading until 19:00 in
     * Harare should see that evening's cuts on that day's total, whatever UTC
     * calls it. Accepts any Carbon; the app runs on CarbonImmutable.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function shopDay(Branch $branch, ?CarbonInterface $date): array
    {
        $timezone = $branch->timezone ?: config('magnetic.timezone');

        $day = CarbonImmutable::instance(
            ($date ?? CarbonImmutable::now($timezone))->toDateTime()
        )->setTimezone($timezone);

        return [$day, $day->startOfDay()->utc(), $day->endOfDay()->utc()];
    }

    /**
     * Every barber's earnings for one day at one branch, busiest first.
     *
     * @return array<int, array{staff_id: string, name: string, services: int, clients: int, earned: array{cents: int, currency: string, amount: float, formatted: string}, took: array{cents: int, currency: string, amount: float, formatted: string}}>
     */
    public function dailyEarnings(Branch $branch, ?CarbonInterface $date = null): array
    {
        [, $from, $to] = $this->shopDay($branch, $date);

        $rows = $this->earnedLines($branch)
            ->whereBetween('appointments.completed_at', [$from, $to])
            ->groupBy('appointment_services.staff_id', 'users.ulid', 'users.name')
            ->select([
                'users.ulid as staff_ulid',
                'users.name as staff_name',
                DB::raw('COUNT(*) as service_count'),
                DB::raw('COUNT(DISTINCT appointments.client_id) as client_count'),
                DB::raw('SUM(appointment_services.commission_cents) as earned_cents'),
                DB::raw('SUM(appointment_services.price_cents * appointment_services.qty) as taken_cents'),
            ])
            ->orderByDesc('earned_cents')
            ->get();

        $currency = config('magnetic.reporting_currency');

        return $rows->map(fn ($row): array => [
            'staff_id' => (string) $row->staff_ulid,
            'name' => (string) $row->staff_name,
            'services' => (int) $row->service_count,
            'clients' => (int) $row->client_count,
            'earned' => Money::ofCents((int) $row->earned_cents, $currency)->toArray(),
            'took' => Money::ofCents((int) $row->taken_cents, $currency)->toArray(),
        ])->all();
    }

    /**
     * One barber's own day: what the app shows a barber about themselves.
     *
     * @return array{date: string, services: int, clients: int, earned: array{cents: int, currency: string, amount: float, formatted: string}, took: array{cents: int, currency: string, amount: float, formatted: string}}
     */
    public function dayFor(User $staff, Branch $branch, ?CarbonInterface $date = null): array
    {
        [$day, $from, $to] = $this->shopDay($branch, $date);
        $currency = config('magnetic.reporting_currency');

        $row = $this->earnedLines($branch)
            ->where('appointment_services.staff_id', $staff->id)
            ->whereBetween('appointments.completed_at', [$from, $to])
            ->select([
                DB::raw('COUNT(*) as service_count'),
                DB::raw('COUNT(DISTINCT appointments.client_id) as client_count'),
                DB::raw('COALESCE(SUM(appointment_services.commission_cents), 0) as earned_cents'),
                DB::raw('COALESCE(SUM(appointment_services.price_cents * appointment_services.qty), 0) as taken_cents'),
            ])
            ->first();

        return [
            'date' => $day->toDateString(),
            'services' => (int) ($row->service_count ?? 0),
            'clients' => (int) ($row->client_count ?? 0),
            'earned' => Money::ofCents((int) ($row->earned_cents ?? 0), $currency)->toArray(),
            'took' => Money::ofCents((int) ($row->taken_cents ?? 0), $currency)->toArray(),
        ];
    }

    /**
     * One barber's earnings day by day, for the earnings screen in the app.
     *
     * @return array<int, array{date: string, services: int, earned: array{cents: int, currency: string, amount: float, formatted: string}}>
     */
    public function runFor(User $staff, Branch $branch, CarbonInterface $from, CarbonInterface $to): array
    {
        $timezone = $branch->timezone ?: config('magnetic.timezone');
        $currency = config('magnetic.reporting_currency');

        [, $fromUtc] = $this->shopDay($branch, $from);
        [, , $toUtc] = $this->shopDay($branch, $to);

        /*
         * Grouped in PHP rather than SQL. Grouping by a date is a per driver
         * expression, and worse, the shop's day is not UTC's day: a 19:00
         * Harare cut belongs to that evening, not to the next morning.
         */
        return $this->earnedLines($branch)
            ->where('appointment_services.staff_id', $staff->id)
            ->whereBetween('appointments.completed_at', [$fromUtc, $toUtc])
            ->select([
                'appointments.completed_at',
                'appointment_services.commission_cents',
            ])
            ->get()
            ->groupBy(fn ($line): string => CarbonImmutable::parse($line->completed_at)->setTimezone($timezone)->toDateString())
            ->map(fn ($lines, string $date): array => [
                'date' => $date,
                'services' => $lines->count(),
                'earned' => Money::ofCents((int) $lines->sum('commission_cents'), $currency)->toArray(),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /**
     * Lines that have actually been earned: completed work, at this branch,
     * attributed to somebody.
     *
     * @return Builder
     */
    private function earnedLines(Branch $branch)
    {
        return DB::table('appointment_services')
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->join('users', 'users.id', '=', 'appointment_services.staff_id')
            ->where('appointments.branch_id', $branch->id)
            ->where('appointments.status', AppointmentStatus::Completed->value)
            ->whereNotNull('appointments.completed_at')
            ->whereNotNull('appointment_services.staff_id');
    }
}
