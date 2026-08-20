<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\ClientSource;
use App\Exceptions\SlotTakenException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BookingService
{
    public function __construct(
        private readonly ClientAccountService $accounts,
        private readonly AvailabilityService $availability,
        private readonly LoyaltyService $loyalty,
        private readonly ReminderService $reminders,
    ) {}

    /**
     * What the wizard shows when someone types a phone number.
     *
     * This deliberately confirms whether a number is already known, because the
     * whole point is "we recognise you, here are your details". It returns the
     * first name only, never the full record, and the route is rate limited.
     *
     * @return array{found: bool, first_name: string|null, account_number: string|null, visit_count: int, last_visit: string|null, preferred_staff_id: string|null, points: int}
     */
    public function lookup(string $phone): array
    {
        $user = $this->accounts->findByPhone($phone);

        if ($user === null) {
            return [
                'found' => false,
                'first_name' => null,
                'account_number' => null,
                'visit_count' => 0,
                'last_visit' => null,
                'preferred_staff_id' => null,
                'points' => 0,
            ];
        }

        $profile = $user->clientProfile()->first();

        // A staff member's number is a real user with no client profile, so
        // none of these can be assumed present.
        $accountNumber = null;
        $visitCount = 0;
        $lastVisit = null;
        $preferredStaff = null;

        if ($profile instanceof ClientProfile) {
            $accountNumber = $profile->account_number;
            $visitCount = $profile->visit_count;
            $lastVisit = $profile->last_visit_at?->toDateString();
            $preferredStaff = $profile->preferredStaff()->value('ulid');
        }

        return [
            'found' => true,
            // First name only: enough to say "welcome back, Tendai", not enough
            // to harvest identities by guessing numbers.
            'first_name' => explode(' ', trim($user->name))[0],
            'account_number' => $accountNumber,
            'visit_count' => $visitCount,
            'last_visit' => $lastVisit,
            'preferred_staff_id' => $preferredStaff === null ? null : (string) $preferredStaff,
            'points' => $this->loyalty->balanceFor($user),
        ];
    }

    /**
     * Create the appointment. Everything that has to be true at once happens
     * inside one transaction with the conflicting rows locked.
     *
     * @param  array{name: string, phone: string, service_ids: list<int>, staff_id: int|null, start: string, style_id: int|null, note: string|null, type?: AppointmentType, source?: string, redeem_points?: bool, created_by?: int|null, address?: array{address_line: string, area: string|null, directions_note: string|null}|null}  $data
     */
    public function book(Branch $branch, array $data): Appointment
    {
        $type = $data['type'] ?? AppointmentType::Scheduled;
        $address = $data['address'] ?? null;

        if ($type === AppointmentType::HouseCall) {
            if (! $branch->house_call_enabled) {
                throw new SlotTakenException('This branch does not do house calls.');
            }

            if ($address === null || blank($address['address_line'])) {
                throw new SlotTakenException('A house call needs an address.');
            }
        }

        $start = Carbon::parse($data['start'])->utc();
        $duration = $this->availability->requiredMinutes($branch, $data['service_ids']);

        if ($duration === 0) {
            throw new SlotTakenException('Those services are not available at this branch.');
        }

        $end = $start->copy()->addMinutes($duration);

        if ($start->isBefore(now())) {
            throw new SlotTakenException('That time has already passed.');
        }

        return DB::transaction(function () use ($branch, $data, $start, $end, $duration, $type, $address): Appointment {
            $client = $this->accounts->register(
                $data['name'],
                $data['phone'],
                $branch,
                ClientSource::Web,
            );

            $staffId = $data['staff_id']
                ?? $this->pickStaff($branch, $start, $end, $data['service_ids'], $type);

            if ($staffId === null) {
                throw new SlotTakenException('Every barber is busy at that time.');
            }

            $this->assertSlotFree($staffId, $start, $end);

            $services = $branch->services()
                ->whereIn('services.id', $data['service_ids'])
                ->wherePivot('is_active', true)
                ->get();

            $subtotal = $services->reduce(
                fn (Money $carry, Service $service): Money => $carry->plus(
                    $service->priceForLoadedBranch() ?? Money::ofCents(0, $carry->currency)
                ),
                Money::ofCents(0, config('magnetic.default_currency')),
            );

            // The travel fee is a flat branch rate for now. Distance zones
            // replace this without the booking flow changing shape.
            $travelFee = $type === AppointmentType::HouseCall
                ? $branch->houseCallFee()
                : Money::ofCents(0, $subtotal->currency);

            // Points come off the services, not the travel: a house call fee
            // is a cost the shop actually incurs.
            $redemption = ($data['redeem_points'] ?? false) === true
                ? $this->loyalty->plannedRedemption($client, $subtotal)
                : null;

            $discount = $redemption['discount']
                ?? Money::ofCents(0, $subtotal->currency);

            $appointment = Appointment::create([
                'reference' => Appointment::generateReference(),
                'branch_id' => $branch->id,
                'client_id' => $client->id,
                'staff_id' => $staffId,
                'type' => $type,
                'status' => AppointmentStatus::Confirmed,
                // Where the booking came from, for the channel report.
                'source' => $data['source'] ?? 'web',
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'style_id' => $data['style_id'] ?? null,
                'client_note' => $data['note'] ?? null,
                'subtotal_cents' => $subtotal->cents,
                'travel_fee_cents' => $travelFee->cents,
                'discount_cents' => $discount->cents,
                'total_cents' => $subtotal->plus($travelFee)->minus($discount)->cents,
                'currency' => $subtotal->currency,
                'duration_minutes' => $duration,
                'created_by' => $client->id,
            ]);

            // The guard above already refused a house call without one.
            if ($type === AppointmentType::HouseCall) {
                // Snapshotted, so editing a saved address later cannot change
                // where a barber was already sent.
                $appointment->houseCall()->create([
                    'address_line' => $address['address_line'],
                    'area' => $address['area'] ?? null,
                    'city' => $branch->city,
                    'directions_note' => $address['directions_note'] ?? null,
                    'travel_fee_cents' => $travelFee->cents,
                    'currency' => $travelFee->currency,
                ]);
            }

            // Price and name are frozen here. A price change next month must
            // not rewrite what this client was quoted today.
            foreach ($services as $service) {
                $price = $service->priceForLoadedBranch() ?? Money::ofCents(0, $subtotal->currency);

                $appointment->services()->create([
                    'service_id' => $service->id,
                    'staff_id' => $staffId,
                    'name_snapshot' => $service->name,
                    'price_cents' => $price->cents,
                    'currency' => $price->currency,
                    'duration_minutes' => $service->durationForLoadedBranch(),
                    'qty' => 1,
                ]);
            }

            if ($redemption !== null) {
                $this->loyalty->redeem(
                    $client,
                    $redemption['points'],
                    $appointment,
                    $data['created_by'] ?? null,
                );
            }

            // They have booked, so stop chasing them.
            $this->reminders->cancelFor($client->id);

            return $appointment->load(['branch', 'staff.staffProfile', 'services', 'client', 'houseCall']);
        });
    }

    /**
     * The locked read is what actually stops a double booking. The unique index
     * on (staff_id, scheduled_start_at) catches the exact duplicate; this
     * catches an overlap that starts at a different minute.
     */
    private function assertSlotFree(int $staffId, Carbon $start, Carbon $end): void
    {
        $conflict = Appointment::query()
            ->where('staff_id', $staffId)
            ->blocking()
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new SlotTakenException;
        }
    }

    /**
     * "Any barber" resolves to the first free one at that exact time.
     *
     * @param  list<int>  $serviceIds
     */
    private function pickStaff(
        Branch $branch,
        Carbon $start,
        Carbon $end,
        array $serviceIds,
        AppointmentType $type = AppointmentType::Scheduled,
    ): ?int {
        // Only barbers who have agreed to travel can take a house call.
        $candidates = $this->availability->bookableStaff(
            $branch,
            $serviceIds,
            null,
            $type === AppointmentType::HouseCall,
        );

        foreach ($candidates as $member) {
            $busy = Appointment::query()
                ->where('staff_id', $member->id)
                ->blocking()
                ->where('scheduled_start_at', '<', $end)
                ->where('scheduled_end_at', '>', $start)
                ->lockForUpdate()
                ->exists();

            if (! $busy) {
                return $member->id;
            }
        }

        return null;
    }

    /**
     * Someone's upcoming bookings, for the "you already have one booked" nudge.
     *
     * @return Collection<int, Appointment>
     */
    public function upcomingFor(string $phone): Collection
    {
        $user = User::query()->where('phone', Phone::normalise($phone))->first();

        if ($user === null) {
            return Appointment::query()->whereRaw('1 = 0')->get();
        }

        return Appointment::query()
            ->where('client_id', $user->id)
            ->upcoming()
            ->with(['branch', 'staff.staffProfile', 'services'])
            ->orderBy('scheduled_start_at')
            ->get();
    }
}
