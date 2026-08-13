<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The account you log into the admin with while building.
 *
 * The password is deliberately weak and this seeder refuses to run in
 * production, because a known password on an owner account is a live door.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('AdminUserSeeder skipped: never seed a known password into production.');

            return;
        }

        $branch = Branch::query()->orderBy('sort_order')->first();

        $admin = User::updateOrCreate(
            ['email' => 'admin@magneticbarber.co.zw'],
            [
                'name' => 'Magnetic Admin',
                'phone' => '+263780000001',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        if ($branch !== null) {
            $admin->branches()->syncWithoutDetaching([
                $branch->id => [
                    'employment_type' => 'employed',
                    'currency' => 'USD',
                    'is_primary' => true,
                    'starts_on' => now()->toDateString(),
                ],
            ]);

            // Roles are global rows; the branch travels on the assignment.
            setPermissionsTeamId($branch->id);
            $admin->syncRoles(['owner']);
            setPermissionsTeamId(null);
        }

        StaffProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'slug' => 'magnetic-admin',
                'display_name' => 'Magnetic Admin',
                'title' => 'Owner',
                'is_bookable' => false,
                // An admin account is not a barber, so it stays off the team page.
                'show_on_site' => false,
                'sort_order' => 99,
            ],
        );

        $this->command->info('Admin ready: admin@magneticbarber.co.zw / password');
    }
}
