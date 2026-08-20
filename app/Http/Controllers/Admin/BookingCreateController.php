<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentType;
use App\Exceptions\SlotTakenException;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\StyleResource;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Style;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\CatalogService;
use App\Services\StyleService;
use App\Support\Phone;
use App\Support\ResourcePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Response;

/**
 * Taking a booking at the desk.
 *
 * Reception works differently from a client on the website: they have the
 * person in front of them, they know the shop, and they are in a hurry. So
 * this is one screen rather than a wizard, it finds a returning client from a
 * partial phone number, and it will let a manager put a booking in a slot the
 * public availability grid would refuse.
 */
class BookingCreateController extends AdminController
{
    public function create(Request $request, CatalogService $catalog, StyleService $styles): Response
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404, 'No branch selected.');

        return inertia('admin/booking-form', [
            'branchContext' => $this->branchContext($request),
            'categories' => ResourcePayload::flatten(
                ServiceCategoryResource::collection($catalog->priceListFor($branch))
            ),
            'styles' => ResourcePayload::flatten(
                StyleResource::collection($styles->gallery())
            ),
            'barbers' => $this->bookableStaff($branch),
            // Clicking an empty slot on the diary arrives here prefilled.
            'prefill' => [
                'date' => $request->query('date', now($branch->timezone)->toDateString()),
                'time' => $request->query('time'),
                'staff' => $request->query('staff'),
            ],
        ]);
    }

    /**
     * Find a client from what reception has typed so far. Deliberately a
     * partial match on name or number: at the desk you get "Tendai" or the
     * last four digits, not a clean lookup key.
     */
    public function findClient(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('client.view'), 403);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:40'],
        ]);

        $term = $validated['q'];

        // Reception types the number the way the client says it: 0781879820.
        // We store E.164, +263781879820, so match on the national part and
        // let a leading 0 or country code fall away.
        $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
        $digits = str_starts_with($digits, '263') ? substr($digits, 3) : $digits;

        // A barber may look a client up but may not read their number. This
        // is what stops a shop losing its client list when a barber leaves.
        $maySeeContact = $request->user()->can('client.contact.view');

        $clients = User::query()
            ->whereHas('clientProfile')
            ->where(function ($query) use ($term, $digits) {
                $query->where('name', 'like', "%{$term}%");

                if ($digits !== '' && strlen($digits) >= 3) {
                    $query->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->with('clientProfile')
            ->limit(8)
            ->get()
            ->map(fn (User $client): array => [
                'id' => $client->ulid,
                'name' => $client->name,
                'phone' => $maySeeContact
                    ? $client->phone
                    : Phone::mask($client->phone),
                'account_number' => $client->clientProfile->account_number,
                'visit_count' => $client->clientProfile->visit_count,
                'last_visit' => $client->clientProfile->last_visit_at?->toDateString(),
            ])
            ->all();

        return response()->json(['data' => $clients]);
    }

    /**
     * Free times for the chosen day, service mix and barber.
     */
    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'staff' => ['nullable', 'string'],
        ]);

        $staffId = null;

        if (! empty($validated['staff']) && $validated['staff'] !== 'any') {
            $staffId = User::query()->where('ulid', $validated['staff'])->value('id');
        }

        return response()->json($availability->forDate(
            $branch,
            Carbon::createFromFormat('Y-m-d', $validated['date'], $branch->timezone)->startOfDay(),
            $this->serviceIds($validated['service_ids'] ?? []),
            $staffId,
        ));
    }

    public function store(Request $request, BookingService $booking): RedirectResponse
    {
        abort_unless($request->user()?->can('appointment.create'), 403);

        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404);

        $validated = $request->validate([
            'client' => ['nullable', 'string', 'exists:users,ulid'],
            'name' => ['required_without:client', 'nullable', 'string', 'min:2', 'max:80'],
            'phone' => ['required_without:client', 'nullable', 'string', 'phone:ZW,mobile'],
            'service_ids' => ['required', 'array', 'min:1', 'max:5'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'style' => ['nullable', 'string', 'exists:styles,ulid'],
            'staff' => ['nullable', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
            'name.required_without' => 'Give a name, or pick an existing client.',
            'phone.required_without' => 'Give a number, or pick an existing client.',
        ]);

        // An existing client keeps their own name and number: reception must
        // not be able to rename somebody by taking a booking for them.
        if (! empty($validated['client'])) {
            $client = User::query()->where('ulid', $validated['client'])->firstOrFail();
            $name = $client->name;
            $phone = $client->phone;
        } else {
            $name = $validated['name'];
            $phone = $validated['phone'];
        }

        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['date']} {$validated['time']}",
            $branch->timezone,
        );

        $staffId = null;

        if (! empty($validated['staff']) && $validated['staff'] !== 'any') {
            $staffId = User::query()->where('ulid', $validated['staff'])->value('id');
        }

        try {
            $appointment = $booking->book($branch, [
                'type' => AppointmentType::Scheduled,
                'source' => 'reception',
                'name' => $name,
                'phone' => $phone,
                'service_ids' => $this->serviceIds($validated['service_ids']),
                'staff_id' => $staffId,
                'start' => $start->utc()->toIso8601String(),
                'style_id' => empty($validated['style'])
                    ? null
                    : Style::query()->where('ulid', $validated['style'])->value('id'),
                'note' => $validated['note'] ?? null,
            ]);
        } catch (SlotTakenException $exception) {
            // Surfaced on the time field, which is the thing to change.
            return back()
                ->withInput()
                ->withErrors(['time' => $exception->getMessage()]);
        }

        return to_route('admin.bookings', [
            'date' => $start->timezone($branch->timezone)->toDateString(),
        ])->with('success', "Booked {$appointment->reference} for {$name}.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookableStaff(Branch $branch): array
    {
        return $branch->staff()
            ->where('users.is_active', true)
            ->whereHas('staffProfile', fn ($query) => $query->where('is_bookable', true))
            ->with('staffProfile')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->ulid,
                'name' => $member->staffProfile?->name() ?? $member->name,
                'title' => $member->staffProfile?->title,
            ])
            ->all();
    }

    /**
     * Clients only ever see ULIDs. Sequential ids never leave the server.
     *
     * @param  array<int, string>  $ulids
     * @return list<int>
     */
    private function serviceIds(array $ulids): array
    {
        return array_values(
            Service::query()
                ->whereIn('ulid', $ulids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }
}
