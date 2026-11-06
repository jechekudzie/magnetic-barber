<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Style;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Booking a chair from the app.
 *
 * Booking does not require an account, and deliberately so: the website has
 * never asked for one, and making somebody sign up before they can book is
 * how a barbershop loses the booking. A client gives a name and a number, and
 * the account is created by that first booking.
 *
 * Signing in with a one time code is therefore not a door anyone has to walk
 * through. It exists so a returning client can read and cancel their own
 * bookings, which is the only part that needs the number to be proven.
 */
class BookingController extends Controller
{
    /**
     * The slot grid for one day.
     */
    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'date' => ['required', 'date_format:Y-m-d'],
            'service_ids' => ['nullable', 'array', 'max:5'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'staff' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:scheduled,house_call'],
        ]);

        $branch = Branch::query()->where('slug', $validated['branch'])->sole();

        return response()->json($availability->forDate(
            $branch,
            CarbonImmutable::createFromFormat('Y-m-d', $validated['date'], $branch->timezone)->startOfDay(),
            $this->serviceIdsFrom($validated['service_ids'] ?? []),
            $this->staffIdFrom($validated['staff'] ?? null),
            ($validated['type'] ?? 'scheduled') === 'house_call',
        ));
    }

    /**
     * The first day this branch can actually take these services.
     *
     * Opening the picker on today is wrong most evenings: the shop shuts at
     * 19:00, so from about six o'clock there is nowhere left to put a cut and
     * a client lands on an empty grid that reads as a broken app. The website
     * already opens on the first bookable date for this reason; this is the
     * same answer for the app.
     */
    public function firstBookableDate(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'service_ids' => ['nullable', 'array', 'max:5'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'type' => ['nullable', 'string', 'in:scheduled,house_call'],
        ]);

        $branch = Branch::query()->where('slug', $validated['branch'])->sole();

        $date = $availability->firstBookableDate(
            $branch,
            $this->serviceIdsFrom($validated['service_ids'] ?? []),
            ($validated['type'] ?? 'scheduled') === 'house_call',
        );

        return response()->json([
            'date' => $date,
            'today' => CarbonImmutable::now($branch->timezone)->toDateString(),
            // Null means nothing is bookable in the next fortnight at all,
            // which is a "call the shop" answer rather than an empty grid.
            'has_availability' => $date !== null,
        ]);
    }

    /**
     * "Do we know this number?"
     *
     * Asked while a client is typing their number into the booking form, so
     * somebody who has walked in before is greeted by name instead of being
     * treated as a stranger and told, again, what the shop already has on
     * file. It is also the moment they have a reason to want an account:
     * their visits and points are already there.
     *
     * It returns a first name and counts, never the full record. Confirming
     * whether a number is known is the point of the feature, so the route is
     * rate limited and gives nothing that would reward guessing numbers.
     */
    public function lookup(Request $request, BookingService $booking): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
        ]);

        $known = $booking->lookup($validated['phone']);

        return response()->json([
            'data' => $known,
            'upcoming' => AppointmentResource::collection(
                $booking->upcomingFor($validated['phone'])
            ),
            /*
             * Whether there is a password on the account. A client the shop
             * knows but who has never signed in is exactly who should be
             * offered one, and somebody who already has one should be offered
             * to sign in rather than told to register again.
             */
            'has_account' => $this->hasPassword($validated['phone']),
        ]);
    }

    private function hasPassword(string $phone): bool
    {
        return User::query()
            ->where('phone', Phone::normalise($phone))
            ->whereNotNull('password')
            ->exists();
    }

    /**
     * Book without an account, exactly as the website does.
     *
     * The name and number come from the body here because there is no account
     * to take them from yet. ClientAccountService::register finds an existing
     * client before creating one, so a regular booking this way lands on their
     * real record and keeps their history and points intact rather than
     * quietly starting a second file on them.
     *
     * This carries the same exposure the website does: nothing proves the
     * number belongs to whoever typed it. It is rate limited, and it is why
     * reading or cancelling a booking needs a verified number even though
     * making one does not.
     */
    public function storeAsGuest(Request $request, BookingService $booking): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'service_ids' => ['required', 'array', 'min:1', 'max:5'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'staff' => ['nullable', 'string'],
            'start' => ['required', 'date'],
            'style' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:scheduled,house_call'],
            'address_line' => ['required_if:type,house_call', 'nullable', 'string', 'max:160'],
            'area' => ['nullable', 'string', 'max:120'],
            'directions_note' => ['nullable', 'string', 'max:300'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
            'address_line.required_if' => 'We need an address to send a barber to.',
        ]);

        $branch = Branch::query()->where('slug', $validated['branch'])->sole();
        $type = AppointmentType::from($validated['type']);

        $appointment = $booking->book($branch, [
            'type' => $type,
            'address' => $type === AppointmentType::HouseCall ? [
                'address_line' => $validated['address_line'],
                'area' => $validated['area'] ?? null,
                'directions_note' => $validated['directions_note'] ?? null,
            ] : null,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'service_ids' => $this->serviceIdsFrom($validated['service_ids']),
            'staff_id' => $this->staffIdFrom($validated['staff'] ?? null),
            'start' => $validated['start'],
            'style_id' => blank($validated['style'] ?? null)
                ? null
                : Style::query()->where('ulid', $validated['style'])->value('id'),
            'note' => $validated['note'] ?? null,
            'source' => 'app',
        ]);

        return response()->json([
            'data' => new AppointmentResource($appointment),
            /*
             * Whether this number has been here before, so the app can say
             * "welcome back" rather than treating a regular as a stranger.
             * Deliberately not their details: nothing has been proven yet.
             */
            'meta' => [
                'is_returning_client' => $appointment->client?->clientProfile?->visit_count > 0,
            ],
        ], 201);
    }

    /**
     * The signed in client's own bookings, soonest first.
     */
    public function index(Request $request, BookingService $booking): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:upcoming,past,all'],
        ]);

        $query = Appointment::query()
            ->where('client_id', $request->user()->id)
            ->with(['branch', 'staff.staffProfile', 'services', 'houseCall']);

        $appointments = match ($validated['scope'] ?? 'upcoming') {
            'past' => $query->where('scheduled_start_at', '<', now())
                ->orderByDesc('scheduled_start_at')->get(),
            'all' => $query->orderByDesc('scheduled_start_at')->get(),
            default => $query->upcoming()->orderBy('scheduled_start_at')->get(),
        };

        return AppointmentResource::collection(
            $appointments->each(fn (Appointment $a) => $a->setAttribute(
                'client_may_cancel',
                $booking->clientMayCancel($a),
            ))
        );
    }

    public function show(Request $request, Appointment $appointment, BookingService $booking): JsonResponse
    {
        $this->assertOwnedBy($request, $appointment);

        $appointment->load(['branch', 'staff.staffProfile', 'services', 'houseCall']);
        $appointment->setAttribute('client_may_cancel', $booking->clientMayCancel($appointment));

        return response()->json(['data' => new AppointmentResource($appointment)]);
    }

    /**
     * Make a booking.
     *
     * The name and phone come from the signed in account rather than the
     * request body: the number was verified at sign in, and letting the body
     * override it would make the whole verification pointless.
     */
    public function store(Request $request, BookingService $booking): JsonResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'service_ids' => ['required', 'array', 'min:1', 'max:5'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'staff' => ['nullable', 'string'],
            'start' => ['required', 'date'],
            'style' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:scheduled,house_call'],
            'redeem_points' => ['nullable', 'boolean'],
            'address_line' => ['required_if:type,house_call', 'nullable', 'string', 'max:160'],
            'area' => ['nullable', 'string', 'max:120'],
            'directions_note' => ['nullable', 'string', 'max:300'],
        ], [
            'address_line.required_if' => 'We need an address to send a barber to.',
        ]);

        $client = $request->user();
        $branch = Branch::query()->where('slug', $validated['branch'])->sole();
        $type = AppointmentType::from($validated['type']);

        $appointment = $booking->book($branch, [
            'type' => $type,
            'address' => $type === AppointmentType::HouseCall ? [
                'address_line' => $validated['address_line'],
                'area' => $validated['area'] ?? null,
                'directions_note' => $validated['directions_note'] ?? null,
            ] : null,
            'name' => $client->name,
            'phone' => $client->phone,
            'service_ids' => $this->serviceIdsFrom($validated['service_ids']),
            'staff_id' => $this->staffIdFrom($validated['staff'] ?? null),
            'start' => $validated['start'],
            'style_id' => blank($validated['style'] ?? null)
                ? null
                : Style::query()->where('ulid', $validated['style'])->value('id'),
            'note' => $validated['note'] ?? null,
            'redeem_points' => (bool) ($validated['redeem_points'] ?? false),
            'source' => 'app',
            'created_by' => $client->id,
        ]);

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ], 201);
    }

    /**
     * Call a booking off.
     */
    public function cancel(Request $request, Appointment $appointment, BookingService $booking): JsonResponse
    {
        $this->assertOwnedBy($request, $appointment);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        abort_if(
            $appointment->status === AppointmentStatus::Completed,
            422,
            'That visit has already happened.',
        );

        abort_if(
            ! $booking->clientMayCancel($appointment),
            422,
            'It is too late to cancel this online. Please call the shop.',
        );

        $cancelled = $booking->cancel($appointment, $request->user(), $validated['reason'] ?? null);

        return response()->json([
            'data' => new AppointmentResource(
                $cancelled->load(['branch', 'staff.staffProfile', 'services'])
            ),
        ]);
    }

    /**
     * A booking that is not yours should look like one that does not exist,
     * not like one you are not allowed to see.
     */
    private function assertOwnedBy(Request $request, Appointment $appointment): void
    {
        abort_if($appointment->client_id !== $request->user()->id, 404);
    }

    /**
     * Clients only ever see ULIDs. Sequential ids never leave the server.
     *
     * @param  array<int, string>  $ulids
     * @return list<int>
     */
    private function serviceIdsFrom(array $ulids): array
    {
        return array_values(
            Service::query()->whereIn('ulid', $ulids)->pluck('id')
                ->map(fn ($id): int => (int) $id)->all()
        );
    }

    private function staffIdFrom(?string $ulid): ?int
    {
        if (blank($ulid) || $ulid === 'any') {
            return null;
        }

        $id = User::query()->where('ulid', $ulid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
