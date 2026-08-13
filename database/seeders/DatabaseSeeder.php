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
        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class,
            CatalogSeeder::class,
            StyleSeeder::class,
            PlanSeeder::class,
            TeamSeeder::class,
        ]);

        // Development conveniences and placeholder copy, never in production.
        if (! app()->isProduction()) {
            $this->call([
                ReviewSeeder::class,
                AdminUserSeeder::class,
                // Sample bookings last, so staff and prices already exist.
                BookingSeeder::class,
            ]);
        }
    }
}
