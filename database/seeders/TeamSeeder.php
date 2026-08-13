<?php

namespace Database\Seeders;

use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * PLACEHOLDER STAFF. Names, bios and photos are stand ins so the team page
 * renders. Replace with the real barbers before the site goes live.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('slug', 'harare-avenues')->firstOrFail();

        $staff = [
            [
                'name' => 'Tapiwa Moyo',
                'email' => 'owner@magneticbarbershop.co.zw',
                'phone' => '+263781879820',
                'role' => 'owner',
                'title' => 'Owner and Master Barber',
                'bio' => 'Opened the first chair in the Avenues and still cuts six days a week.',
                'specialities' => ['Skin fades', 'Beard sculpting', 'Hot towel shaves'],
                'employment' => EmploymentType::Employed,
                'house_calls' => true,
            ],
            [
                'name' => 'Rudo Chikwanha',
                'email' => 'manager@magneticbarbershop.co.zw',
                'phone' => '+263782000001',
                'role' => 'branch-manager',
                'title' => 'Branch Manager',
                'bio' => 'Runs the floor, the diary and the till. Knows every regular by name.',
                'specialities' => ['Colour', 'Grey blending'],
                'employment' => EmploymentType::Employed,
                'house_calls' => false,
            ],
            [
                'name' => 'Blessing Ncube',
                'email' => null,
                'phone' => '+263782000002',
                'role' => 'barber',
                'title' => 'Senior Barber',
                'bio' => 'Fades and waves. Books out first every Saturday, so book ahead.',
                'specialities' => ['Waves', 'Low fades', 'Kids cuts'],
                'employment' => EmploymentType::ChairRental,
                'house_calls' => true,
            ],
            [
                'name' => 'Nyasha Dube',
                'email' => null,
                'phone' => '+263782000003',
                'role' => 'aesthetician',
                'title' => 'Aesthetician, The Skin Room',
                'bio' => 'Runs the skin room. Facials, ingrown treatment and the six session programme.',
                'specialities' => ['Deep cleanse facials', 'Razor bump treatment'],
                'employment' => EmploymentType::Employed,
                'house_calls' => false,
            ],
            [
                'name' => 'Farai Sibanda',
                'email' => null,
                'phone' => '+263782000004',
                'role' => 'receptionist',
                'title' => 'Front Desk',
                'bio' => 'First face through the door. Logs every walk in and keeps the queue honest.',
                'specialities' => [],
                'employment' => EmploymentType::Employed,
                'house_calls' => false,
                'show_on_site' => false,
            ],
        ];

        $second = Branch::where('slug', 'borrowdale')->first();

        foreach ($staff as $order => $member) {
            $user = User::updateOrCreate(
                ['phone' => $member['phone']],
                [
                    'name' => $member['name'],
                    'email' => $member['email'],
                    'email_verified_at' => $member['email'] ? now() : null,
                    'phone_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->branches()->syncWithoutDetaching([
                $branch->id => [
                    'employment_type' => $member['employment']->value,
                    'commission_rate' => $member['employment'] === EmploymentType::ChairRental ? null : 30,
                    'chair_rate_cents' => $member['employment'] === EmploymentType::ChairRental ? 5000 : null,
                    'currency' => 'USD',
                    'is_primary' => true,
                    'starts_on' => now()->subYear()->toDateString(),
                ],
            ]);

            // Roles are global rows; the assignment carries the branch.
            setPermissionsTeamId($branch->id);
            $user->assignRole($member['role']);

            // The owner and one barber also work the second branch, which is
            // what makes the branch switcher meaningful.
            if ($second !== null && in_array($member['role'], ['owner', 'barber'], true)) {
                $user->branches()->syncWithoutDetaching([
                    $second->id => [
                        'employment_type' => $member['employment']->value,
                        'currency' => 'USD',
                        'is_primary' => false,
                        'starts_on' => now()->subMonths(6)->toDateString(),
                    ],
                ]);

                setPermissionsTeamId($second->id);
                $user->assignRole($member['role']);
            }

            StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'slug' => Str::slug($member['name']),
                    'display_name' => $member['name'],
                    'title' => $member['title'],
                    'bio' => $member['bio'],
                    'specialities' => $member['specialities'],
                    'accepts_house_calls' => $member['house_calls'],
                    'is_bookable' => $member['role'] !== 'receptionist',
                    'show_on_site' => $member['show_on_site'] ?? true,
                    'sort_order' => $order,
                ],
            );
        }

        setPermissionsTeamId(null);
    }
}
