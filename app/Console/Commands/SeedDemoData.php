<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Review;
use App\Models\StaffProfile;
use Database\Seeders\BookingSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Sample barbers, reviews and bookings, for showing the system working before
 * there is real trade in it.
 *
 * This is deliberately a command rather than part of db:seed. Invented people
 * and invented bookings on a live site are a decision somebody has to make on
 * purpose, not something a routine deploy does quietly.
 */
class SeedDemoData extends Command
{
    protected $signature = 'magnetic:demo-data {--force : Skip the confirmation}';

    protected $description = 'Add sample staff, reviews and bookings for a demo';

    public function handle(): int
    {
        $live = app()->isProduction();

        if ($live) {
            $this->warn('This is a PRODUCTION environment.');
            $this->line('  It will add invented barbers, invented reviews and roughly 200');
            $this->line('  invented bookings, all visible to real visitors.');
            $this->newLine();
        }

        if (! $this->option('force') && ! confirm(
            label: $live ? 'Add sample data to the live site?' : 'Add sample data?',
            default: ! $live,
        )) {
            $this->line('Nothing added.');

            return self::SUCCESS;
        }

        $this->callSilent('db:seed', ['--class' => TeamSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => ReviewSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => BookingSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info(sprintf(
            'Sample data ready: %d staff, %d reviews, %d bookings.',
            StaffProfile::count(),
            Review::count(),
            Appointment::count(),
        ));

        $this->newLine();
        $this->warn('  There is no clean way to unpick this from real data later.');
        $this->line('  Put it on a staging or demo site, and start production empty.');

        return self::SUCCESS;
    }
}
