<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\ReminderService;
use Illuminate\Console\Command;

/**
 * Queues a win back reminder for everyone who has gone too long without a cut.
 *
 * It queues rather than sends: there is no messaging integration yet, and
 * pretending otherwise would leave the shop believing clients had been
 * contacted when nobody had. The admin shows the queue with a WhatsApp link
 * per client, which works today.
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--branch= : Only this branch slug}';

    protected $description = 'Queue win back reminders for clients who are overdue a cut';

    public function handle(ReminderService $reminders): int
    {
        $branch = null;

        if ($this->option('branch') !== null) {
            $branch = Branch::query()->where('slug', $this->option('branch'))->first();

            if ($branch === null) {
                $this->error("No branch with the slug {$this->option('branch')}.");

                return self::FAILURE;
            }
        }

        $board = $reminders->board($branch);
        $queued = $reminders->schedule($branch);

        $this->info(sprintf(
            '%d overdue, %d nearly due. Queued %d new reminder%s (threshold %d days).',
            count($board['due']),
            count($board['soon']),
            $queued,
            $queued === 1 ? '' : 's',
            $board['threshold'],
        ));

        if ($queued === 0 && count($board['due']) > 0) {
            $this->line('  Everyone overdue already has a reminder waiting.');
        }

        return self::SUCCESS;
    }
}
