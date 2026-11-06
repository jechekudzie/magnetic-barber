<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchSequence;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CommissionService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

beforeEach(function () {
    $this->branch = Branch::factory()->create([
        'code' => 'MB',
        'timezone' => 'Africa/Harare',
        'opens_at' => '08:00',
        'closes_at' => '19:00',
        'days_open' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    BranchSequence::create(['branch_id' => $this->branch->id, 'last_account_number' => 0]);

    $this->barber = User::factory()->create(['name' => 'Tapiwa Nyoni']);
    StaffProfile::factory()->for($this->barber)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($this->barber, ['is_primary' => true]);

    // A $10 cut, which is what the price card actually charges.
    $this->cut = Service::factory()->create(['default_duration_minutes' => 45, 'buffer_minutes' => 0]);
    $this->branch->services()->attach($this->cut, [
        'price_cents' => 1000,
        'currency' => 'USD',
        'duration_minutes' => 45,
        'is_active' => true,
    ]);

    $this->commission = app(CommissionService::class);
    $this->booking = app(BookingService::class);
});

/**
 * Book a cut and mark it done, which is the only way a barber earns anything.
 */
function completedCut(?User $barber = null, ?CarbonInterface $at = null): Appointment
{
    $start = CarbonImmutable::instance(($at ?? now()->addDay()->setTime(10, 0))->toDateTime());

    $appointment = test()->booking->book(test()->branch, [
        'name' => 'Tendai Moyo',
        'phone' => '078187'.fake()->numberBetween(1000, 9999),
        'service_ids' => [test()->cut->id],
        'staff_id' => ($barber ?? test()->barber)->id,
        'start' => $start->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);

    /*
     * ->utc() matters. Eloquent formats a datetime in whatever timezone the
     * instance carries, so handing it a Harare time writes those wall clock
     * digits and reads them back as UTC. The app's own code reaches this
     * column through now(), which is already UTC.
     */
    $appointment->update([
        'status' => AppointmentStatus::Completed,
        'completed_at' => $start->utc(),
    ]);

    return $appointment;
}

it('pays a barber five percent of the line they rendered', function () {
    $appointment = completedCut();
    $line = $appointment->services()->sole();

    expect($line->commission_rate)->toEqual(5.0)
        ->and($line->commission_cents)->toBe(50)
        ->and($line->commission()->format())->toBe('$0.50');
});

it('records who rendered each line, not just who the booking was with', function () {
    $appointment = completedCut();

    expect($appointment->services()->sole()->staff_id)->toBe($this->barber->id);
});

it('freezes the rate, so changing it later does not rewrite what was earned', function () {
    $appointment = completedCut();

    $this->branch->staff()->updateExistingPivot($this->barber->id, ['commission_rate' => 50]);

    expect($appointment->services()->sole()->commission_cents)->toBe(50)
        ->and($this->commission->dailyEarnings($this->branch, now()->addDay())[0]['earned']['cents'])->toBe(50);
});

it('prefers a barber\'s own rate over the house rate', function () {
    $this->branch->staff()->updateExistingPivot($this->barber->id, ['commission_rate' => 10]);

    expect($this->commission->rateFor($this->barber, $this->branch))->toBe(10.0);
});

it('falls back to the house rate when a barber has none of their own', function () {
    expect($this->commission->rateFor($this->barber, $this->branch))->toBe(5.0);
});

it('rounds the commission to the cent', function () {
    // 5% of $14.99 is 74.95c.
    expect($this->commission->commissionOn(Money::ofCents(1499), 5)->cents)->toBe(75);
});

it('counts nothing until the visit is completed', function () {
    $this->booking->book($this->branch, [
        'name' => 'Tendai Moyo',
        'phone' => '0781879820',
        'service_ids' => [$this->cut->id],
        'staff_id' => $this->barber->id,
        'start' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'style_id' => null,
        'note' => null,
    ]);

    expect($this->commission->dailyEarnings($this->branch, now()->addDay()))->toBe([]);
});

it('pays nothing on a cancelled visit, without zeroing the line', function () {
    $appointment = completedCut();
    $appointment->update(['status' => AppointmentStatus::Cancelled]);

    expect($this->commission->dailyEarnings($this->branch, now()->addDay()))->toBe([])
        // Still on the row, so reinstating the booking needs no recalculation.
        ->and($appointment->services()->sole()->commission_cents)->toBe(50);
});

it('adds up a barber\'s day and counts the clients they saw', function () {
    $day = now()->addDay()->setTime(9, 0);

    completedCut(at: $day);
    completedCut(at: $day->addHours(2));
    completedCut(at: $day->addHours(4));

    $earnings = $this->commission->dayFor($this->barber, $this->branch, $day);

    expect($earnings['services'])->toBe(3)
        ->and($earnings['clients'])->toBe(3)
        ->and($earnings['earned']['cents'])->toBe(150)
        ->and($earnings['took']['cents'])->toBe(3000)
        ->and($earnings['earned']['formatted'])->toBe('$1.50');
});

it('keeps each barber\'s earnings separate and ranks them', function () {
    $other = User::factory()->create(['name' => 'Rudo Chikafu']);
    StaffProfile::factory()->for($other)->create(['is_bookable' => true]);
    $this->branch->staff()->attach($other, ['is_primary' => true]);

    $day = now()->addDay()->setTime(9, 0);

    completedCut(at: $day);
    completedCut(at: $day->addHours(2));
    completedCut($other, $day->addHour());

    $earnings = $this->commission->dailyEarnings($this->branch, $day);

    expect($earnings)->toHaveCount(2)
        ->and($earnings[0]['name'])->toBe('Tapiwa Nyoni')
        ->and($earnings[0]['earned']['cents'])->toBe(100)
        ->and($earnings[1]['name'])->toBe('Rudo Chikafu')
        ->and($earnings[1]['earned']['cents'])->toBe(50);
});

it('counts an evening cut on the shop\'s day, not UTC\'s', function () {
    // 19:00 in Harare is 17:00 UTC the same day, but a 23:30 Harare cut is
    // 21:30 UTC — still the same day. The one that matters is a cut taken
    // after midnight UTC, which Harare still calls the previous evening.
    $harareEvening = CarbonImmutable::parse('2027-03-04 22:30', 'Africa/Harare');

    completedCut(at: $harareEvening);

    $theDay = $this->commission->dayFor($this->barber, $this->branch, $harareEvening);
    $nextDay = $this->commission->dayFor($this->barber, $this->branch, $harareEvening->addDay());

    expect($theDay['date'])->toBe('2027-03-04')
        ->and($theDay['earned']['cents'])->toBe(50)
        ->and($nextDay['earned']['cents'])->toBe(0);
});

it('reports a barber\'s run of days, most recent first', function () {
    $monday = CarbonImmutable::parse('2027-03-01 10:00', 'Africa/Harare');

    completedCut(at: $monday);
    completedCut(at: $monday->addDay());
    completedCut(at: $monday->addDay()->addHours(2));

    $run = $this->commission->runFor(
        $this->barber,
        $this->branch,
        $monday->subDay(),
        $monday->addDays(2),
    );

    expect($run)->toHaveCount(2)
        ->and($run[0]['date'])->toBe('2027-03-02')
        ->and($run[0]['services'])->toBe(2)
        ->and($run[0]['earned']['cents'])->toBe(100)
        ->and($run[1]['date'])->toBe('2027-03-01')
        ->and($run[1]['earned']['cents'])->toBe(50);
});

it('reports an empty day rather than failing when a barber did nothing', function () {
    $day = $this->commission->dayFor($this->barber, $this->branch, now());

    expect($day['services'])->toBe(0)
        ->and($day['clients'])->toBe(0)
        ->and($day['earned']['cents'])->toBe(0);
});
