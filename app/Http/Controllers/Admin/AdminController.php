<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Support\Money;
use App\Support\Phone;
use App\Support\ResourcePayload;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared behaviour for admin screens. Every screen is scoped to one branch,
 * because prices, stock and staff are all per branch.
 */
abstract class AdminController extends Controller
{
    /**
     * The branch the admin is currently acting in. SetPermissionsBranch has
     * already validated that the user works there before it was stored.
     */
    protected function currentBranch(Request $request): ?Branch
    {
        if (app()->bound('currentBranch')) {
            return app('currentBranch');
        }

        return $request->user()?->branches()
            ->orderByDesc('branch_user.is_primary')
            ->first();
    }

    /**
     * Branch context shared with every admin page so the switcher renders.
     *
     * @return array<string, mixed>
     */
    protected function branchContext(Request $request): array
    {
        $current = $this->currentBranch($request);

        $branches = $request->user()?->branches()->orderBy('sort_order')->get()
            ?? collect();

        return [
            'current' => $current === null
                ? null
                : ResourcePayload::flatten(new BranchResource($current)),
            'available' => ResourcePayload::flatten(BranchResource::collection($branches)),
        ];
    }

    /**
     * One booking, shaped for an admin screen.
     *
     * Shared by the diary and the front desk, because the two show the same
     * booking and must not describe it differently: a reference that reads
     * one way on the grid and another at the desk is how a client gets told
     * two different things in one visit.
     *
     * @return array<string, mixed>
     */
    protected function appointmentRow(
        Appointment $appointment,
        string $timezone,
        bool $maySeeContact = false,
    ): array {
        $start = $appointment->scheduled_start_at?->copy()->timezone($timezone);
        $end = $appointment->scheduled_end_at?->copy()->timezone($timezone);

        // Read off the loaded relation rather than re-querying per row, which
        // on a full day's diary was a query per booking.
        $profile = $appointment->client?->relationLoaded('clientProfile')
            ? $appointment->client->clientProfile
            : $appointment->client?->clientProfile;

        return [
            'id' => $appointment->ulid,
            'reference' => $appointment->reference,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'type' => $appointment->type->value,
            'is_house_call' => $appointment->isHouseCall(),
            'branch' => $appointment->branch?->slug,
            'branch_name' => $appointment->branch?->name,
            'date' => $start?->toDateString(),
            'day_label' => $start?->format('D j M'),
            'time_label' => $start?->format('g:ia'),
            // Minutes from midnight, so the grid can place a block without
            // parsing dates in the browser.
            'start_minutes' => $start !== null ? ($start->hour * 60 + $start->minute) : 0,
            'end_minutes' => $end !== null ? ($end->hour * 60 + $end->minute) : 0,
            'duration_minutes' => $appointment->duration_minutes,
            'staff_id' => $appointment->staff?->ulid,
            'staff' => $appointment->staff?->staffProfile?->name() ?? $appointment->staff?->name,
            'client' => [
                'name' => $appointment->client?->name,
                // Reception and managers need this to chase a no show. A
                // barber gets it masked, by design.
                'phone' => $maySeeContact
                    ? $appointment->client?->phone
                    : Phone::mask($appointment->client?->phone),
                'account_number' => $profile?->account_number,
                'visit_count' => $profile === null ? 0 : $profile->visit_count,
            ],
            'services' => $appointment->services->pluck('name_snapshot')->all(),
            'total' => Money::ofCents($appointment->total_cents, $appointment->currency)->toArray(),
            'address' => $appointment->houseCall?->fullAddress(),
            'note' => $appointment->client_note,
            // The barber's clock, and whether the money actually arrived.
            'started_at' => $appointment->started_at?->toIso8601String(),
            'minutes_so_far' => $appointment->minutesSoFar(),
            'paid' => $appointment->amountPaid()->toArray(),
            'outstanding' => $appointment->outstanding()->toArray(),
            'is_secured' => $appointment->isSecured(),
            'is_paid_in_full' => $appointment->isPaidInFull(),
        ];
    }

    /**
     * A booking at a branch this user does not work at is not theirs to touch.
     */
    protected function assertReachable(Request $request, Appointment $appointment): void
    {
        $reachable = $this->reachableBranches($request)->pluck('id');

        abort_unless($reachable->contains($appointment->branch_id), 404);
    }

    /**
     * The branches this user may look at. An owner sees the group; everyone
     * else only the branches they actually work at.
     *
     * @return Collection<int, Branch>
     */
    protected function reachableBranches(Request $request): Collection
    {
        $user = $request->user();

        if ($user === null) {
            return collect();
        }

        if ($user->hasRole('owner') || $user->hasRole('super-admin')) {
            return Branch::query()->active()->ordered()->get();
        }

        return $user->branches()->where('is_active', true)->orderBy('sort_order')->get();
    }
}
