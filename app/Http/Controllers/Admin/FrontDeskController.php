<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Services\VisitService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response;

/**
 * The front desk.
 *
 * Someone walks in. They either booked online or they did not, and either
 * way the first thing the desk does is find them: a number, a name, or a
 * reference read off their phone. The diary cannot do this, because the
 * diary is a day and a walk-in is a person — somebody who booked Thursday
 * and turned up Wednesday is invisible on a day grid.
 *
 * So this screen searches across every date, then gives the three actions
 * that follow: put them in a chair, start the clock, close the visit.
 */
class FrontDeskController extends AdminController
{
    public function index(Request $request, VisitService $visits): Response
    {
        $branches = $this->reachableBranches($request);

        abort_if($branches->isEmpty(), 403, 'You are not assigned to a branch.');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:60'],
        ]);

        $term = trim($validated['search'] ?? '');
        $timezone = $branches->first()->timezone ?: config('magnetic.timezone');
        $maySeeContact = $request->user()?->can('client.contact.view') ?? false;

        $hits = $term === ''
            ? collect()
            : $visits->search($branches->pluck('id')->values()->all(), $term);

        return inertia('admin/desk', [
            'branchContext' => $this->branchContext($request),
            'search' => $term,
            'results' => $hits
                ->map(fn (Appointment $appointment): array => [
                    ...$this->appointmentRow($appointment, $timezone, $maySeeContact),
                    'can_assign' => $this->mayAssign($request, $appointment),
                    'assigned_by' => $appointment->assignedBy?->name,
                ])
                ->all(),
            // Today's expected arrivals, so an empty search box is still the
            // most useful screen in the shop rather than a blank page.
            'expected' => $this->expectedToday($branches, $timezone, $maySeeContact, $request),
            'barbers' => $this->barbers($branches),
            'can_assign' => $request->user()?->can('appointment.assign') ?? false,
        ]);
    }

    /**
     * Put this client in this barber's chair.
     *
     * Reception's job, and the reason it is a separate action from editing
     * the booking: the auto-assigned barber is frequently not the one who is
     * free when the client actually arrives, and moving them has to carry the
     * commission with it.
     */
    public function assign(
        Request $request,
        Appointment $appointment,
        VisitService $visits,
    ): RedirectResponse {
        $this->assertReachable($request, $appointment);

        $validated = $request->validate([
            'staff_id' => ['required', 'string', 'exists:users,ulid'],
        ]);

        $barber = User::query()->where('ulid', $validated['staff_id'])->firstOrFail();

        abort_unless($this->mayAssign($request, $appointment, $barber), 403);

        $visits->assign($appointment, $barber, $request->user());

        return back()->with(
            'success',
            "{$appointment->reference} is {$barber->name}'s.",
        );
    }

    /**
     * Who may move this booking into whose chair.
     *
     * Reception and managers may move anything. A barber may only ever take a
     * booking onto themselves, which is the case the shop actually has when
     * the desk is unattended: the client is waiting, the barber is free, and
     * the alternative is cutting under somebody else's name and paying the
     * wrong person.
     */
    private function mayAssign(Request $request, Appointment $appointment, ?User $barber = null): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('appointment.assign')) {
            return true;
        }

        if ($barber !== null && $barber->id !== $user->id) {
            return false;
        }

        return app(VisitService::class)->mayClaim($appointment, $user);
    }

    /**
     * Everyone due in today, whether or not anybody searched.
     *
     * Narrowed to the barber's own column unless they can see the branch
     * diary, because a list of everybody due in is the book, and keeping one
     * barber out of another's book is deliberate. Searching is different: a
     * barber looking up the person in front of them is answering a question,
     * not browsing, so search is not narrowed.
     *
     * @param  Collection<int, Branch>  $branches
     * @return array<int, array<string, mixed>>
     */
    private function expectedToday(
        Collection $branches,
        string $timezone,
        bool $maySeeContact,
        Request $request,
    ): array {
        $today = CarbonImmutable::now($timezone);

        return Appointment::query()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->whereBetween('scheduled_start_at', [
                $today->startOfDay()->utc(),
                $today->endOfDay()->utc(),
            ])
            ->whereNotIn('status', [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->when(
                $request->user()?->can('appointment.view.branch') !== true,
                fn ($query) => $query->where('staff_id', $request->user()?->id),
            )
            ->with([
                'branch:id,name,slug,timezone',
                'client.clientProfile',
                'staff.staffProfile',
                'services',
                'payments',
                'houseCall',
            ])
            ->orderBy('scheduled_start_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                ...$this->appointmentRow($appointment, $timezone, $maySeeContact),
                'can_assign' => $this->mayAssign($request, $appointment),
                'assigned_by' => $appointment->assignedBy?->name,
            ])
            ->all();
    }

    /**
     * The chairs this booking could move to, with what each barber is
     * already holding today, because "who is free" is the actual question.
     *
     * @param  Collection<int, Branch>  $branches
     * @return array<int, array<string, mixed>>
     */
    private function barbers(Collection $branches): array
    {
        $branchIds = $branches->pluck('id');

        return User::query()
            ->whereHas('branches', fn ($query) => $query->whereIn('branches.id', $branchIds))
            ->whereHas('staffProfile', fn ($query) => $query->where('is_bookable', true))
            ->with('staffProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->ulid,
                'name' => $user->staffProfile?->name() ?? $user->name,
                'title' => $user->staffProfile?->title,
                // Null until they tap Start, which is what makes this useful:
                // a barber with a live clock is not free, whatever the diary
                // says about their next slot.
                'in_chair' => Appointment::query()
                    ->where('staff_id', $user->id)
                    ->where('status', AppointmentStatus::InProgress)
                    ->whereNotNull('started_at')
                    ->value('reference'),
            ])
            ->all();
    }
}
