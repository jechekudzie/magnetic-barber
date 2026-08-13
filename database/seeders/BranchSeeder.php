<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchSequence;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $this->avenues();
        $this->borrowdale();
    }

    /**
     * The original shop.
     */
    private function avenues(): void
    {
        $branch = Branch::updateOrCreate(
            ['slug' => 'harare-avenues'],
            [
                'code' => 'MB',
                'name' => 'Harare Avenues',
                'tagline' => 'Where every cut is a game changer.',
                'phone' => '+263781879820',
                'whatsapp' => '+263781879820',
                'email' => 'hello@magneticbarbershop.co.zw',
                'address_line' => 'Devonshire House, Room 6',
                'area' => 'Corner Josiah Chinamano and Blackstone Avenue, Avenues',
                'city' => 'Harare',
                // Approximate. Drop a real map pin before go live.
                'latitude' => -17.8203,
                'longitude' => 31.0448,
                'directions_note' => 'Ground floor, take the entrance on Blackstone. Parking on the street.',
                'timezone' => 'Africa/Harare',
                'opens_at' => '08:00',
                'closes_at' => '19:00',
                // 1 is Monday through 6 Saturday. Closed Sunday.
                'days_open' => [1, 2, 3, 4, 5, 6],
                'chair_count' => 8,
                'house_call_enabled' => true,
                'house_call_radius_km' => 25,
                'house_call_fee_cents' => 500,
                // Narrower than the shop: the barber has to travel there and back.
                'house_call_opens_at' => '09:00',
                'house_call_closes_at' => '17:00',
                'house_call_days_open' => [1, 2, 3, 4, 5, 6],
                'sort_order' => 0,
                'is_active' => true,
            ],
        );

        BranchSequence::firstOrCreate(
            ['branch_id' => $branch->id],
            ['last_account_number' => 0],
        );
    }

    /**
     * A second branch, so the per branch behaviour is exercised rather than
     * assumed: its own account prefix, its own prices, its own hours.
     */
    private function borrowdale(): void
    {
        $branch = Branch::updateOrCreate(
            ['slug' => 'borrowdale'],
            [
                'code' => 'BD',
                'name' => 'Borrowdale',
                'tagline' => 'Northside chair, same standard.',
                'phone' => '+263782000010',
                'whatsapp' => '+263782000010',
                'email' => 'borrowdale@magneticbarbershop.co.zw',
                'address_line' => 'Shop 4, Sam Levy Village',
                'area' => 'Borrowdale',
                'city' => 'Harare',
                // Approximate. Drop a real map pin before go live.
                'latitude' => -17.7519,
                'longitude' => 31.0872,
                'directions_note' => 'Upper level, past the fountain.',
                'timezone' => 'Africa/Harare',
                'opens_at' => '09:00',
                'closes_at' => '18:00',
                'days_open' => [1, 2, 3, 4, 5, 6, 0],
                'chair_count' => 5,
                'house_call_enabled' => false,
                'house_call_fee_cents' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        BranchSequence::firstOrCreate(
            ['branch_id' => $branch->id],
            ['last_account_number' => 0],
        );
    }
}
