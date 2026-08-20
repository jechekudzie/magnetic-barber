<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClientProfile;
use App\Models\ReminderSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReminderService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ReminderController extends AdminController
{
    public function index(Request $request, ReminderService $reminders): Response
    {
        $branch = $this->currentBranch($request);
        $board = $reminders->board($branch);

        // A barber may see who has lapsed but never the number itself. Chasing
        // a client is reception's job, and the client list is the shop's.
        $maySeeContact = $request->user()?->can('client.contact.view') ?? false;
        $template = $reminders->messageTemplate($branch?->id);

        $present = fn (array $row): array => [
            ...$row,
            'phone' => $maySeeContact ? $row['phone'] : Phone::mask($row['phone']),
            'phone_display' => $maySeeContact
                ? $row['phone_display']
                : Phone::mask($row['phone']),
            'whatsapp_number' => $maySeeContact ? $row['whatsapp_number'] : null,
            'message' => $reminders->renderMessage($row, $template),
        ];

        return inertia('admin/reminders', [
            'branchContext' => $this->branchContext($request),
            'can' => [
                'see_contact' => $maySeeContact,
                'send' => $request->user()?->can('message.send') ?? false,
                'manage_settings' => $request->user()?->can('settings.manage') ?? false,
            ],
            'settings' => [
                'threshold' => $board['threshold'],
                'warn' => $board['warn'],
                'horizon' => ReminderService::HORIZON_DAYS,
                'message' => $template,
            ],
            'due' => array_map($present, $board['due']),
            'soon' => array_map($present, $board['soon']),
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
            'message' => ['required', 'string', 'min:10', 'max:600'],
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

        Setting::put(
            ReminderService::SETTING_MESSAGE_KEY,
            $validated['message'],
            null,
            $request->user()->id,
        );

        ReminderService::forgetCount();

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

        ReminderService::forgetCount();

        return back()->with('success', "Updated how often {$client->name} is chased.");
    }

    /**
     * Reception has just opened WhatsApp for this client. Record it so the
     * row shows as chased and nobody messages them twice in a morning.
     */
    public function markClientMessaged(Request $request, ReminderService $reminders): RedirectResponse
    {
        abort_unless($request->user()?->can('message.send'), 403);

        $validated = $request->validate([
            'client' => ['required', 'string', 'exists:users,ulid'],
            'days_since_visit' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $client = User::query()->where('ulid', $validated['client'])->firstOrFail();

        $pending = ReminderSchedule::query()
            ->where('client_id', $client->id)
            ->pending()
            ->first();

        if ($pending !== null) {
            $pending->update(['sent_at' => now()]);
        } else {
            ReminderSchedule::create([
                'client_id' => $client->id,
                'branch_id' => $this->currentBranch($request)?->id,
                'type' => ReminderService::TYPE_WINBACK,
                'due_at' => now(),
                'sent_at' => now(),
                'days_since_visit' => $validated['days_since_visit'] ?? null,
            ]);
        }

        return back()->with('success', "Logged a reminder to {$client->name}.");
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
