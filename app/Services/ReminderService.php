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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

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

    /** The words the shop uses when it chases somebody. */
    public const SETTING_MESSAGE_KEY = 'winback_message';

    /**
     * How far ahead the board looks. Wider than the warn window so the screen
     * can offer "next 3 weeks" without going back to the database.
     */
    public const HORIZON_DAYS = 28;

    public const DEFAULT_MESSAGE = 'Hi {name}, it has been {days} days since your last cut at {shop}. Want us to book you in this week?';

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

    public function messageTemplate(?int $branchId = null): string
    {
        return (string) Setting::get(self::SETTING_MESSAGE_KEY, self::DEFAULT_MESSAGE, $branchId);
    }

    /**
     * Fill the shop's template in for one client. Kept here so the screen, the
     * nightly job and any future WhatsApp integration all say the same thing.
     *
     * @param  array<string, mixed>  $client
     */
    public function renderMessage(array $client, ?string $template = null, ?string $shop = null): string
    {
        $first = explode(' ', trim((string) $client['name']))[0];

        return strtr($template ?? self::DEFAULT_MESSAGE, [
            '{name}' => $first,
            '{days}' => (string) $client['days_since'],
            '{shop}' => $shop ?? config('app.name'),
            '{branch}' => (string) ($client['branch'] ?? $shop ?? config('app.name')),
        ]);
    }

    /**
     * The sidebar badge is cached, so anything that changes who is overdue has
     * to say so. Otherwise reception books somebody and the badge argues.
     */
    public static function forgetCount(): void
    {
        Cache::forget('reminders.due.all');

        foreach (Branch::query()->pluck('id') as $branchId) {
            Cache::forget('reminders.due.'.$branchId);
        }
    }

    /**
     * Just the number for the sidebar badge. One aggregate rather than the
     * whole board, because it runs on every admin page.
     */
    public function dueCount(?Branch $branch = null): int
    {
        $threshold = $this->thresholdDays($branch?->id);

        $query = $this->lapsedQuery($branch);

        // "Overdue by the client's own cycle, or the shop's if they have none."
        // Written per driver so both stay literal SQL.
        DB::connection()->getDriverName() === 'sqlite'
            ? $query->whereRaw("last_visit_at <= datetime('now', '-' || COALESCE(preferred_cycle_days, ?) || ' days')", [$threshold])
            : $query->whereRaw('last_visit_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL COALESCE(preferred_cycle_days, ?) DAY)', [$threshold]);

        return $query->count();
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

        $profiles = $this->lapsedQuery($branch)
            ->with(['user', 'homeBranch'])
            ->get();

        // One query for who has already been chased, rather than one per row.
        $messaged = ReminderSchedule::query()
            ->whereNotNull('sent_at')
            ->whereIn('client_id', $profiles->pluck('user_id'))
            ->selectRaw('client_id, MAX(sent_at) as last_sent')
            ->groupBy('client_id')
            ->pluck('last_sent', 'client_id');

        $rows = $profiles
            ->map(fn (ClientProfile $profile): array => $this->assess($profile, $threshold, $messaged))
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
                ->filter(fn (array $row): bool => $row['days_over'] < 0 && $row['days_over'] >= -self::HORIZON_DAYS)
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
        self::forgetCount();

        return ReminderSchedule::query()
            ->where('client_id', $clientId)
            ->pending()
            ->update(['cancelled_at' => now(), 'cancelled_reason' => $reason]);
    }

    /**
     * @param  Collection<int, mixed>  $messaged
     * @return array<string, mixed>
     */
    private function assess(ClientProfile $profile, int $shopThreshold, Collection $messaged): array
    {
        // What the client asked for beats the shop default.
        $threshold = $profile->preferred_cycle_days ?? $shopThreshold;
        $days = (int) $profile->last_visit_at->diffInDays(now(), true);
        $over = $days - $threshold;

        return [
            'client_id' => $profile->user_id,
            'id' => $profile->user?->ulid,
            'name' => $profile->user?->name,
            'phone' => $profile->user?->phone,
            'phone_display' => Phone::forDisplay($profile->user?->phone),
            'whatsapp_number' => Phone::forWhatsAppLink($profile->user?->phone),
            'branch_id' => $profile->home_branch_id,
            'branch' => $profile->homeBranch?->name,
            'account_number' => $profile->account_number,
            'visit_count' => $profile->visit_count,
            'last_visit' => $profile->last_visit_at->toDateString(),
            'days_since' => $days,
            'threshold' => $threshold,
            'days_over' => $over,
            'days_until' => max(0, -$over),
            'due_on' => $profile->last_visit_at->copy()->addDays($threshold)->toDateString(),
            'last_messaged' => ($messaged[$profile->user_id] ?? null) === null
                ? null
                : Date::parse($messaged[$profile->user_id])->toDateString(),
            'preferred_cycle_days' => $profile->preferred_cycle_days,
            'average_cycle_days' => $profile->average_cycle_days,
            'marketing_opt_in' => $profile->marketing_opt_in,
        ];
    }

    /**
     * Everyone who could lapse: has been in, still wants chasing, and has
     * nothing already in the diary. Somebody already booked is not lapsed,
     * whatever the gap.
     *
     * @return Builder<ClientProfile>
     */
    private function lapsedQuery(?Branch $branch): Builder
    {
        return ClientProfile::query()
            ->whereNotNull('last_visit_at')
            ->where('visit_count', '>', 0)
            ->where('reminders_enabled', true)
            ->when($branch !== null, fn ($query) => $query->where('home_branch_id', $branch->id))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('appointments')
                    ->whereColumn('appointments.client_id', 'client_profiles.user_id')
                    ->whereIn('appointments.status', AppointmentStatus::blocking())
                    ->where('appointments.scheduled_start_at', '>=', now());
            });
    }
}
