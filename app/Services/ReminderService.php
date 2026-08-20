<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\ReminderSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Support\Phone;

/**
 * Who has stopped coming, and who is about to.
 *
 * The threshold is whatever the shop sets, unless a client has told us their
 * own rhythm. Somebody who comes weekly is overdue long before somebody who
 * comes monthly, so their word beats the default.
 */
final class ReminderService
{
    public const TYPE_WINBACK = 'winback';

    /** Shop-wide setting key, editable by the owner. */
    public const SETTING_KEY = 'winback_days';

    /** How many days ahead of the threshold somebody counts as almost due. */
    public const SETTING_WARN_KEY = 'winback_warn_days';

    public function thresholdDays(?int $branchId = null): int
    {
        return (int) Setting::get(
            self::SETTING_KEY,
            (int) config('magnetic.winback_days', 21),
            $branchId,
        );
    }

    public function warnDays(?int $branchId = null): int
    {
        return (int) Setting::get(self::SETTING_WARN_KEY, 5, $branchId);
    }

    /**
     * Everyone with a visit behind them, split into who is late and who is
     * nearly there, so the shop can chase one list and watch the other.
     *
     * @return array{due: array<int, array<string, mixed>>, soon: array<int, array<string, mixed>>, threshold: int, warn: int}
     */
    public function board(?Branch $branch = null): array
    {
        $threshold = $this->thresholdDays($branch?->id);
        $warn = $this->warnDays($branch?->id);

        $rows = ClientProfile::query()
            ->whereNotNull('last_visit_at')
            ->where('visit_count', '>', 0)
            ->where('reminders_enabled', true)
            ->when($branch !== null, fn ($query) => $query->where('home_branch_id', $branch->id))
            ->with(['user', 'homeBranch'])
            ->get()
            ->map(fn (ClientProfile $profile): ?array => $this->assess($profile, $threshold))
            ->filter()
            ->values();

        return [
            'threshold' => $threshold,
            'warn' => $warn,
            'due' => $rows
                ->filter(fn (array $row): bool => $row['days_over'] >= 0)
                ->sortByDesc('days_over')
                ->values()
                ->all(),
            'soon' => $rows
                ->filter(fn (array $row): bool => $row['days_over'] < 0 && $row['days_over'] >= -$warn)
                ->sortBy('days_until')
                ->values()
                ->all(),
        ];
    }

    /**
     * Queue a reminder for everyone who is late and has none waiting.
     */
    public function schedule(?Branch $branch = null): int
    {
        $added = 0;

        foreach ($this->board($branch)['due'] as $client) {
            $waiting = ReminderSchedule::query()
                ->where('client_id', $client['client_id'])
                ->where('type', self::TYPE_WINBACK)
                ->pending()
                ->exists();

            if ($waiting) {
                continue;
            }

            ReminderSchedule::create([
                'client_id' => $client['client_id'],
                'branch_id' => $client['branch_id'],
                'type' => self::TYPE_WINBACK,
                'due_at' => now(),
                'days_since_visit' => $client['days_since'],
            ]);

            $added++;
        }

        return $added;
    }

    /**
     * Recompute a client's observed rhythm from their recent visits.
     *
     * The median, not the mean, so one holiday gap does not push their
     * reminder out by a month. Informational: what they asked for wins.
     */
    public function recomputeCycle(User $client): ?int
    {
        $dates = Appointment::query()
            ->where('client_id', $client->id)
            ->where('status', AppointmentStatus::Completed)
            ->orderByDesc('scheduled_start_at')
            ->limit(6)
            ->pluck('scheduled_start_at');

        if ($dates->count() < 3) {
            return null;
        }

        $gaps = [];

        for ($i = 0; $i < $dates->count() - 1; $i++) {
            $gaps[] = (int) $dates[$i + 1]->diffInDays($dates[$i], true);
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);

        $median = count($gaps) % 2 === 0
            ? (int) round(($gaps[$middle - 1] + $gaps[$middle]) / 2)
            : $gaps[$middle];

        if ($median < 1) {
            return null;
        }

        $client->clientProfile()->update(['average_cycle_days' => $median]);

        return $median;
    }

    /**
     * Booking cancels anything waiting: chasing somebody who has already
     * rebooked is how a shop annoys its regulars.
     */
    public function cancelFor(int $clientId, string $reason = 'Client booked again'): int
    {
        return ReminderSchedule::query()
            ->where('client_id', $clientId)
            ->pending()
            ->update(['cancelled_at' => now(), 'cancelled_reason' => $reason]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assess(ClientProfile $profile, int $shopThreshold): ?array
    {
        // Someone already booked is not lapsed, whatever the gap.
        if ($this->hasUpcoming($profile->user_id)) {
            return null;
        }

        // What the client asked for beats the shop default.
        $threshold = $profile->preferred_cycle_days ?? $shopThreshold;
        $days = (int) $profile->last_visit_at->diffInDays(now(), true);
        $over = $days - $threshold;

        return [
            'client_id' => $profile->user_id,
            'id' => $profile->user?->ulid,
            'name' => $profile->user?->name,
            'phone' => $profile->user?->phone,
            'whatsapp' => $this->whatsAppLink($profile->user, $days),
            'branch_id' => $profile->home_branch_id,
            'branch' => $profile->homeBranch?->name,
            'account_number' => $profile->account_number,
            'visit_count' => $profile->visit_count,
            'last_visit' => $profile->last_visit_at->toDateString(),
            'days_since' => $days,
            'threshold' => $threshold,
            'days_over' => $over,
            'days_until' => max(0, -$over),
            'preferred_cycle_days' => $profile->preferred_cycle_days,
            'average_cycle_days' => $profile->average_cycle_days,
            'marketing_opt_in' => $profile->marketing_opt_in,
        ];
    }

    private function hasUpcoming(int $clientId): bool
    {
        return Appointment::query()
            ->where('client_id', $clientId)
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('scheduled_start_at', '>=', now())
            ->exists();
    }

    /**
     * A link reception can click to message them right now. This works today,
     * with no messaging integration behind it.
     */
    private function whatsAppLink(?User $client, int $days): ?string
    {
        if ($client === null || blank($client->phone)) {
            return null;
        }

        $number = Phone::forWhatsAppLink($client->phone);
        $first = explode(' ', trim($client->name))[0];

        $text = rawurlencode(
            "Hi {$first}, it has been {$days} days since your last cut at Magnetic. "
            .'Want us to book you in this week?'
        );

        return "https://wa.me/{$number}?text={$text}";
    }
}
