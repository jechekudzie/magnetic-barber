<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\ClientSource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientAccountService;
use App\Services\LoyaltyService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SAMPLE BOOKINGS. A month behind and a fortnight ahead, so the dashboard,
 * the bookings screen and the loyalty ledger have something real to show.
 *
 * Everything here goes through the same paths the app uses: completed visits
 * award points and bump the visit counters, so the retention numbers are
 * genuinely derived rather than written in.
 */
class BookingSeeder extends Seeder
{
    /**
     * Placeholder client names. Replace with nothing: real clients arrive
     * through the booking form.
     *
     * @var list<string>
     */
    private array $names = [
        'Tendai Moyo', 'Chipo Nyathi', 'Farai Mutasa', 'Rutendo Banda',
        'Takudzwa Shumba', 'Nyasha Chirwa', 'Tinashe Gumbo', 'Vimbai Sibanda',
        'Kudzai Marufu', 'Panashe Zimuto', 'Anesu Mhlanga', 'Tatenda Chikafu',
        'Simba Mangwiro', 'Ropafadzo Dube', 'Munashe Tavera', 'Chiedza Mapfumo',
        'Tafara Ncube', 'Rumbidzai Kaseke', 'Blessing Zhou', 'Tanaka Mabhena',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('BookingSeeder skipped in production.');

            return;
        }

        $accounts = app(ClientAccountService::class);
        $loyalty = app(LoyaltyService::class);

        $branches = Branch::query()->with(['staff.staffProfile'])->get();

        if ($branches->isEmpty()) {
            return;
        }

        $clients = $this->clients($accounts, $branches->first());

        // Taken slots per staff per start time, so no two bookings collide on
        // the unique slot key the way a real double booking would be refused.
        $taken = [];
        $created = 0;

        foreach ($branches as $branch) {
            $bookable = $branch->staff->filter(
                fn (User $member): bool => (bool) $member->staffProfile?->is_bookable
            )->values();

            if ($bookable->isEmpty()) {
                continue;
            }

            $services = $branch->services()
                ->wherePivot('is_active', true)
                ->get()
                ->filter(function (Service $service): bool {
                    $price = $service->priceForLoadedBranch();

                    return $price !== null && $price->cents > 0;
                })
                ->values();

            if ($services->isEmpty()) {
                continue;
            }

            // Thirty days behind, fourteen ahead.
            for ($offset = -30; $offset <= 14; $offset++) {
                $day = Carbon::now($branch->timezone)->startOfDay()->addDays($offset);

                if (! $branch->isOpenOn((int) $day->dayOfWeek)) {
                    continue;
                }

                // Saturdays are the busy day in a barbershop, so the chart has
                // a shape rather than being flat noise.
                $count = $day->isSaturday()
                    ? random_int(4, 7)
                    : random_int(1, 4);

                for ($i = 0; $i < $count; $i++) {
                    $staff = $bookable->random();
                    $hour = random_int(9, 16);
                    $minute = [0, 15, 30, 45][random_int(0, 3)];
                    $start = $day->copy()->setTime($hour, $minute)->utc();

                    $key = $staff->id.'@'.$start->format('Y-m-d H:i');

                    if (isset($taken[$key])) {
                        continue;
                    }

                    $taken[$key] = true;

                    $this->book(
                        $branch,
                        $staff,
                        $clients->random(),
                        $services->shuffle()->take(random_int(1, 2)),
                        $start,
                        $offset,
                        $loyalty,
                    );

                    $created++;
                }
            }
        }

        $this->command->info("Seeded {$created} sample bookings across {$branches->count()} branches.");
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function book(
        Branch $branch,
        User $staff,
        User $client,
        Collection $services,
        Carbon $start,
        int $dayOffset,
        LoyaltyService $loyalty,
    ): void {
        $duration = (int) $services->sum(
            fn (Service $service): int => $service->durationForLoadedBranch() + $service->buffer_minutes
        );

        $subtotal = $services->reduce(
            fn (Money $carry, Service $service): Money => $carry->plus(
                $service->priceForLoadedBranch() ?? Money::ofCents(0)
            ),
            Money::ofCents(0, config('magnetic.default_currency')),
        );

        $status = $this->statusFor($dayOffset);

        // A house call only where the branch actually offers one, and only
        // from a barber who travels.
        $houseCall = $branch->house_call_enabled
            && $staff->staffProfile?->accepts_house_calls
            && random_int(1, 6) === 1;

        $travelFee = $houseCall ? $branch->houseCallFee() : Money::ofCents(0, $subtotal->currency);

        $appointment = Appointment::create([
            'reference' => Appointment::generateReference(),
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'type' => $houseCall ? AppointmentType::HouseCall : AppointmentType::Scheduled,
            'status' => $status,
            'source' => ['web', 'whatsapp', 'reception', 'app'][random_int(0, 3)],
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addMinutes($duration),
            'completed_at' => $status === AppointmentStatus::Completed ? $start : null,
            'cancelled_at' => in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
                ? $start
                : null,
            'subtotal_cents' => $subtotal->cents,
            'travel_fee_cents' => $travelFee->cents,
            'total_cents' => $subtotal->plus($travelFee)->cents,
            'currency' => $subtotal->currency,
            'duration_minutes' => $duration,
        ]);

        foreach ($services as $service) {
            $price = $service->priceForLoadedBranch() ?? Money::ofCents(0, $subtotal->currency);

            $appointment->services()->create([
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'name_snapshot' => $service->name,
                'price_cents' => $price->cents,
                'currency' => $price->currency,
                'duration_minutes' => $service->durationForLoadedBranch(),
                'qty' => 1,
            ]);
        }

        if ($houseCall) {
            $appointment->houseCall()->create([
                'address_line' => random_int(1, 99).' '.['Northolt Drive', 'Enterprise Road', 'Fife Avenue', 'Josiah Chinamano'][random_int(0, 3)],
                'area' => ['Mount Pleasant', 'Avondale', 'Belgravia', 'Milton Park'][random_int(0, 3)],
                'city' => $branch->city,
                'travel_fee_cents' => $travelFee->cents,
                'currency' => $travelFee->currency,
            ]);
        }

        // A completed visit is what earns points and makes someone a
        // returning client, so it goes through the same path the admin does.
        if ($status === AppointmentStatus::Completed) {
            $this->recordVisit($appointment);
            $loyalty->awardForVisit($appointment);
        }
    }

    /**
     * Past bookings are mostly done, with the odd no show. Future ones are
     * still confirmed.
     */
    private function statusFor(int $dayOffset): AppointmentStatus
    {
        if ($dayOffset > 0) {
            return AppointmentStatus::Confirmed;
        }

        return match (random_int(1, 10)) {
            1 => AppointmentStatus::NoShow,
            2 => AppointmentStatus::Cancelled,
            default => AppointmentStatus::Completed,
        };
    }

    private function recordVisit(Appointment $appointment): void
    {
        $profile = ClientProfile::query()
            ->where('user_id', $appointment->client_id)
            ->first();

        if ($profile === null) {
            return;
        }

        $profile->update([
            'first_visit_at' => $profile->first_visit_at ?? $appointment->scheduled_start_at,
            'last_visit_at' => $appointment->scheduled_start_at,
            'visit_count' => $profile->visit_count + 1,
            'lifetime_value_cents' => $profile->lifetime_value_cents + $appointment->total_cents,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function clients(ClientAccountService $accounts, Branch $branch)
    {
        return collect($this->names)->map(function (string $name, int $index) use ($accounts, $branch): User {
            return $accounts->register(
                $name,
                // A deterministic, obviously fake block of numbers.
                '+26378'.str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                $branch,
                ClientSource::cases()[$index % count(ClientSource::cases())],
            );
        });
    }
}
