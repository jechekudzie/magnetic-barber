<?php

namespace App\Http\Controllers\Site;

use App\Enums\AppointmentType;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Style;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Support\ResourcePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookController extends SiteController
{
    public function show(Request $request, AvailabilityService $availability): Response
    {
        $branch = $this->currentBranch($request);

        // Arriving from "Book this cut" in the gallery. The style and the
        // service it is booked as are both preselected, so the client does not
        // have to find them again in the menu.
        $preselected = null;

        if ($request->filled('style')) {
            $preselected = $this->payload->style(
                (string) $request->query('style'),
                $branch,
            );
        }

        return inertia('site/book', [
            'preselectedStyle' => $preselected,
            'site' => $this->shared($branch),
            'categories' => $branch === null ? [] : $this->payload->priceList($branch),
            'barbers' => $this->payload->team($branch),
            'styles' => $this->payload->styles(),
            // Opening on today is wrong every evening after closing, and an
            // empty time picker reads as a broken one.
            'firstBookableDate' => $branch === null
                ? null
                : $availability->firstBookableDate($branch),
        ]);
    }

    /**
     * "Do we know this number?" The wizard asks this before it asks for a name,
     * so a returning client never retypes what we already hold.
     */
    public function lookup(Request $request, BookingService $booking): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
        ], [
            'phone.phone' => 'That does not look like a Zimbabwean mobile number.',
        ]);

        return response()->json([
            'data' => $booking->lookup($validated['phone']),
            'upcoming' => ResourcePayload::flatten(
                AppointmentResource::collection($booking->upcomingFor($validated['phone']))
            ),
        ]);
    }

    /**
     * The slot grid for one day. Called every time the wizard changes date,
     * barber or service selection.
     */
    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        $branch = $this->currentBranch($request);

        if ($branch === null) {
            throw new NotFoundHttpException('No branch is published yet.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,ulid'],
            'staff' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:scheduled,house_call'],
        ]);

        $staffId = null;

        if (! empty($validated['staff']) && $validated['staff'] !== 'any') {
            $staffId = User::query()
                ->where('ulid', $validated['staff'])
                ->value('id');
        }

        return response()->json($availability->forDate(
            $branch,
            Carbon::createFromFormat('Y-m-d', $validated['date'], $branch->timezone)->startOfDay(),
            $this->serviceIdsFrom($validated['service_ids'] ?? []),
            $staffId,
            ($validated['type'] ?? 'scheduled') === 'house_call',
        ));
    }

    public function store(Request $request, BookingService $booking): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        abort_if($branch === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'phone:ZW,mobile'],
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

        $staffId = null;

        if (! empty($validated['staff']) && $validated['staff'] !== 'any') {
            $staffId = User::query()
                ->where('ulid', $validated['staff'])
                ->value('id');
        }

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
            'staff_id' => $staffId,
            'start' => $validated['start'],
            'style_id' => empty($validated['style'])
                ? null
                : Style::query()->where('ulid', $validated['style'])->value('id'),
            'note' => $validated['note'] ?? null,
        ]);

        return to_route('booking.confirmed', ['appointment' => $appointment->ulid]);
    }

    public function confirmed(Request $request, string $appointment): Response
    {
        $branch = $this->currentBranch($request);

        $booked = Appointment::query()
            ->where('ulid', $appointment)
            ->with(['branch', 'staff.staffProfile', 'services', 'houseCall'])
            ->first();

        if ($booked === null) {
            throw new NotFoundHttpException('Booking not found.');
        }

        return inertia('site/booked', [
            'site' => $this->shared($branch),
            'appointment' => ResourcePayload::flatten(new AppointmentResource($booked)),
        ]);
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
            Service::query()
                ->whereIn('ulid', $ulids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
    }
}
