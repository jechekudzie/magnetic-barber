<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Order matters: roles before staff, branches before prices, services
     * before the styles and plans that point at them.
     */
    public function run(): void
    {
        /*
         * Safe to run against production, and safe to re run: every one of
         * these matches on a slug or a phone number and updates in place.
         *
         * This is real shop data, not sample data. Roles are required for
         * anyone to log in at all.
         */
        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            CatalogSeeder::class,
            StyleSeeder::class,
            PlanSeeder::class,
        ]);

        if (app()->isProduction()) {
            $this->command->info('Catalog seeded. Create staff with: php artisan magnetic:staff');

            return;
        }

        /*
         * Placeholder people, copy and traffic. None of this may reach
         * production: TeamSeeder invents five barbers and gives two of them a
         * known password, which on a live site is an open door.
         */
        $this->call([
            TeamSeeder::class,
            ReviewSeeder::class,
            AdminUserSeeder::class,
            // Sample bookings last, so staff and prices already exist.
            BookingSeeder::class,
        ]);
    }
}
