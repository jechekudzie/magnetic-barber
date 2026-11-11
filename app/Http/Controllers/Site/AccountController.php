<?php

namespace App\Http\Controllers\Site;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\BookingService;
use App\Services\LoyaltyService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * A client's own page on the website.
 *
 * It exists because signing in on the site used to land a client on the
 * staff dashboard: an admin shell they had no permission for, with a
 * sidebar of links that all refused them. Everything here already existed
 * in the app, and the two surfaces are supposed to match.
 */
class AccountController extends SiteController
{
    public function show(Request $request, BookingService $booking, LoyaltyService $loyalty): Response
    {
        $client = $request->user();

        $appointments = Appointment::query()
            ->where('client_id', $client->id)
            ->with(['branch', 'staff.staffProfile', 'services', 'houseCall'])
            ->orderByDesc('scheduled_start_at')
            ->limit(50)
            ->get();

        [$past, $upcoming] = $appointments->partition(
            fn (Appointment $appointment): bool => $appointment->scheduled_start_at?->isPast() ?? true
        );

        $profile = $client->clientProfile;

        return inertia('site/account', [
            'site' => $this->shared($this->currentBranch($request)),
            'client' => [
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'account_number' => $profile?->account_number,
                'visit_count' => $profile === null ? 0 : $profile->visit_count,
                'member_since' => $client->created_at?->format('M Y'),
            ],
            'upcoming' => $upcoming
                ->sortBy('scheduled_start_at')
                ->map(fn (Appointment $appointment): array => $this->row($appointment, $booking))
                ->values()
                ->all(),
            'past' => $past
                ->map(fn (Appointment $appointment): array => $this->row($appointment, $booking))
                ->values()
                ->all(),
            'loyalty' => $loyalty->summaryFor($client),
        ]);
    }

    /**
     * Call off a booking.
     *
     * The window is the shop's, not the client's: BookingService decides how
     * close to the chair is too close, and the same rule answers the app.
     */
    public function cancel(Request $request, Appointment $appointment, BookingService $booking): RedirectResponse
    {
        abort_unless($appointment->client_id === $request->user()->id, 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($appointment->status === AppointmentStatus::Completed) {
            return back()->withErrors(['booking' => 'That visit has already happened.']);
        }

        if (! $booking->clientMayCancel($appointment)) {
            return back()->withErrors([
                'booking' => 'It is too late to cancel this online. Please call the shop.',
            ]);
        }

        $booking->cancel($appointment, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', "{$appointment->reference} is cancelled.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Appointment $appointment, BookingService $booking): array
    {
        $timezone = $appointment->branch?->timezone ?: config('magnetic.timezone');
        $start = $appointment->scheduled_start_at?->copy()->timezone($timezone);

        return [
            'id' => $appointment->ulid,
            'reference' => $appointment->reference,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'is_house_call' => $appointment->isHouseCall(),
            'day_label' => $start?->format('D j M Y'),
            'time_label' => $start?->format('g:ia'),
            'duration_minutes' => $appointment->duration_minutes,
            'branch' => $appointment->branch?->name,
            'address' => $appointment->houseCall?->fullAddress()
                ?? $appointment->branch?->address_line,
            'barber' => $appointment->staff?->staffProfile?->name()
                ?? $appointment->staff?->name,
            'services' => $appointment->services->pluck('name_snapshot')->all(),
            'total' => Money::ofCents($appointment->total_cents, $appointment->currency)->toArray(),
            'paid' => $appointment->amountPaid()->toArray(),
            'outstanding' => $appointment->outstanding()->toArray(),
            'is_secured' => $appointment->isSecured(),
            'is_paid_in_full' => $appointment->isPaidInFull(),
            'may_cancel' => $booking->clientMayCancel($appointment),
            'note' => $appointment->client_note,
        ];
    }
}
