<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClientProfile;
use App\Services\LoyaltyService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class BookingController extends AdminController
{
    /** How many bookings one screen will render before it says so. */
    private const MAX_ROWS = 200;

    public function index(Request $request): Response
    {
        $branch = $this->currentBranch($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:60'],
        ]);

        $timezone = $branch?->timezone ?: config('app.timezone');

        $from = Carbon::parse($validated['from'] ?? now($timezone)->toDateString(), $timezone)->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $from->copy()->addDays(13)->toDateString(), $timezone)->endOfDay();

        $appointments = Appointment::query()
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->whereBetween('scheduled_start_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->when(
                ! empty($validated['status']) && $validated['status'] !== 'all',
                fn ($query) => $query->where('status', $validated['status'])
            )
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $term = $validated['search'];
                $phone = Phone::normalise($term);

                $query->where(function ($q) use ($term, $phone) {
                    $q->where('reference', 'like', "%{$term}%")
                        ->orWhereHas('client', fn ($c) => $c
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', $phone));
                });
            })
            ->with(['client.clientProfile', 'staff.staffProfile', 'services', 'houseCall'])
            ->orderBy('scheduled_start_at');

        // Capped, but the cap is reported rather than silently swallowing
        // bookings a manager would then never know to look for.
        $matching = (clone $appointments)->count();
        $appointments = $appointments->limit(self::MAX_ROWS)->get();

        return inertia('admin/bookings', [
            'branchContext' => $this->branchContext($request),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'status' => $validated['status'] ?? 'all',
                'search' => $validated['search'] ?? '',
            ],
            'statuses' => collect(AppointmentStatus::cases())
                ->map(fn (AppointmentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            'bookings' => $appointments
                ->map(fn (Appointment $appointment): array => $this->row($appointment, $timezone))
                ->all(),
            'summary' => [
                'total' => $matching,
                'shown' => $appointments->count(),
                'truncated' => $matching > $appointments->count(),
                'confirmed' => $appointments->where('status', AppointmentStatus::Confirmed)->count(),
                'completed' => $appointments->where('status', AppointmentStatus::Completed)->count(),
                'cancelled' => $appointments->whereIn('status', [
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])->count(),
                'value' => Money::ofCents(
                    (int) $appointments
                        ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
                        ->sum('total_cents'),
                    config('magnetic.default_currency'),
                )->toArray(),
            ],
        ]);
    }

    /**
     * Moving a booking through its states. Completing it is what earns the
     * client their points, so that write is the one that matters here.
     */
    public function updateStatus(
        Request $request,
        Appointment $appointment,
        LoyaltyService $loyalty,
    ): RedirectResponse {
        abort_unless($request->user()?->can('appointment.update'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,checked_in,in_progress,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $status = AppointmentStatus::from($validated['status']);

        DB::transaction(function () use ($appointment, $status, $validated, $request, $loyalty): void {
            $appointment->update([
                'status' => $status,
                'checked_in_at' => $status === AppointmentStatus::CheckedIn ? now() : $appointment->checked_in_at,
                'started_at' => $status === AppointmentStatus::InProgress ? now() : $appointment->started_at,
                'completed_at' => $status === AppointmentStatus::Completed ? now() : $appointment->completed_at,
                'cancelled_at' => in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
                    ? now()
                    : null,
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_by' => in_array($status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
                    ? $request->user()->id
                    : null,
            ]);

            if ($status === AppointmentStatus::Completed) {
                $this->recordVisit($appointment);
                $loyalty->awardForVisit($appointment->refresh());
            }
        });

        return back()->with('success', "{$appointment->reference} is now {$status->label()}.");
    }

    /**
     * A completed visit is what makes someone a returning client, so the
     * counters that drive retention are updated here rather than on booking.
     */
    private function recordVisit(Appointment $appointment): void
    {
        $profile = ClientProfile::query()
            ->where('user_id', $appointment->client_id)
            ->first();

        if ($profile === null) {
            return;
        }

        $profile->update([
            'first_visit_at' => $profile->first_visit_at ?? now(),
            'last_visit_at' => now(),
            'visit_count' => $profile->visit_count + 1,
            'lifetime_value_cents' => $profile->lifetime_value_cents + $appointment->total_cents,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Appointment $appointment, string $timezone): array
    {
        $start = $appointment->scheduled_start_at?->copy()->timezone($timezone);

        return [
            'id' => $appointment->ulid,
            'reference' => $appointment->reference,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'type' => $appointment->type->value,
            'is_house_call' => $appointment->isHouseCall(),
            'date' => $start?->toDateString(),
            'day_label' => $start?->format('D j M'),
            'time_label' => $start?->format('g:ia'),
            'duration_minutes' => $appointment->duration_minutes,
            'client' => [
                'name' => $appointment->client?->name,
                // Reception and managers need this to chase a no show; the
                // barber-facing screens deliberately never show it.
                'phone' => $appointment->client?->phone,
                'account_number' => $appointment->client?->clientProfile()->value('account_number'),
                'visit_count' => $appointment->client?->clientProfile()->value('visit_count') ?? 0,
            ],
            'staff' => $appointment->staff?->staffProfile?->name() ?? $appointment->staff?->name,
            'services' => $appointment->services->pluck('name_snapshot')->all(),
            'total' => Money::ofCents($appointment->total_cents, $appointment->currency)->toArray(),
            'address' => $appointment->houseCall?->fullAddress(),
            'note' => $appointment->client_note,
        ];
    }
}
