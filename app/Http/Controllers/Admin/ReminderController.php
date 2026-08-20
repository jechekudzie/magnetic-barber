<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClientProfile;
use App\Models\ReminderSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ReminderController extends AdminController
{
    public function index(Request $request, ReminderService $reminders): Response
    {
        $branch = $this->currentBranch($request);
        $board = $reminders->board($branch);

        return inertia('admin/reminders', [
            'branchContext' => $this->branchContext($request),
            'settings' => [
                'threshold' => $board['threshold'],
                'warn' => $board['warn'],
            ],
            'due' => $board['due'],
            'soon' => $board['soon'],
            'queued' => ReminderSchedule::query()
                ->pending()
                ->with('client')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (ReminderSchedule $row): array => [
                    'id' => $row->id,
                    'client' => $row->client?->name,
                    'days_since_visit' => $row->days_since_visit,
                    'queued_at' => $row->created_at?->toDateString(),
                ])
                ->all(),
        ]);
    }

    /**
     * The shop's own rule: how long is too long between cuts.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $validated = $request->validate([
            'threshold' => ['required', 'integer', 'min:1', 'max:365'],
            'warn' => ['required', 'integer', 'min:0', 'max:60'],
        ], [
            'threshold.min' => 'A reminder threshold of less than a day would chase everybody, always.',
        ]);

        Setting::put(
            ReminderService::SETTING_KEY,
            $validated['threshold'],
            null,
            $request->user()->id,
        );

        Setting::put(
            ReminderService::SETTING_WARN_KEY,
            $validated['warn'],
            null,
            $request->user()->id,
        );

        return back()->with(
            'success',
            "Clients are chased after {$validated['threshold']} days."
        );
    }

    /**
     * What one client asked for, which overrides the shop rule for them.
     */
    public function updateClient(Request $request, ReminderService $reminders): RedirectResponse
    {
        abort_unless($request->user()?->can('client.update'), 403);

        $validated = $request->validate([
            'client' => ['required', 'string', 'exists:users,ulid'],
            'preferred_cycle_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'reminders_enabled' => ['required', 'boolean'],
        ]);

        $client = User::query()->where('ulid', $validated['client'])->firstOrFail();

        ClientProfile::query()
            ->where('user_id', $client->id)
            ->update([
                'preferred_cycle_days' => $validated['preferred_cycle_days'],
                'reminders_enabled' => $validated['reminders_enabled'],
            ]);

        // A client who is no longer chased should not have one waiting.
        if (! $validated['reminders_enabled']) {
            $reminders->cancelFor($client->id, 'Reminders switched off for this client');
        }

        return back()->with('success', "Updated how often {$client->name} is chased.");
    }

    /**
     * Mark a queued reminder as dealt with, once somebody has actually
     * messaged them.
     */
    public function markSent(Request $request, ReminderSchedule $reminder): RedirectResponse
    {
        abort_unless($request->user()?->can('message.send'), 403);

        $reminder->update(['sent_at' => now()]);

        return back()->with('success', 'Marked as messaged.');
    }
}
