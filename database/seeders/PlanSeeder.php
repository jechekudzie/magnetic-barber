<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Plan;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::pluck('id', 'slug');

        $plans = [
            [
                'name' => 'Fresh Every Fortnight',
                'tagline' => 'Two cuts a month, one price.',
                'description' => 'A signature cut every two weeks with your barber held for you. Pay once at the start of the month.',
                'type' => PlanType::SessionPack,
                'session_count' => 2,
                'price' => 20,
                'validity_days' => 30,
                'services' => ['signature-cut'],
                'perks' => ['Priority over the walk in queue', 'Free line up between cuts', 'Points still earn on every visit'],
                'popular' => false,
            ],
            [
                'name' => 'Always Sharp',
                'tagline' => 'Unlimited cuts and line ups.',
                'description' => 'Come as often as you like. Cuts, shape ups and beard trims all month, at one branch.',
                'type' => PlanType::Unlimited,
                'session_count' => null,
                'price' => 45,
                'validity_days' => 30,
                'services' => ['signature-cut', 'shape-up', 'beard-trim-and-shape'],
                'perks' => ['Unlimited cuts, shape ups and beard trims', 'Priority booking window', 'Ten percent off products'],
                'popular' => true,
            ],
            [
                'name' => 'Six Session Skin',
                'tagline' => 'A facial becomes a habit, not a one off.',
                'description' => 'Six deep cleanse facials over six months, with your skin profile and photos tracked across all of them.',
                'type' => PlanType::SessionPack,
                'session_count' => 6,
                'price' => 120,
                'validity_days' => 180,
                'services' => ['deep-cleanse-facial'],
                'perks' => ['Six deep cleanse facials', 'Skin profile tracked visit to visit', 'Before and after photos, with consent'],
                'popular' => false,
            ],
        ];

        foreach ($plans as $order => $plan) {
            Plan::updateOrCreate(
                ['slug' => Str::slug($plan['name'])],
                [
                    'name' => $plan['name'],
                    'tagline' => $plan['tagline'],
                    'description' => $plan['description'],
                    'type' => $plan['type'],
                    'included_service_ids' => collect($plan['services'])
                        ->map(fn (string $slug): ?int => $services[$slug] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                    'session_count' => $plan['session_count'],
                    'price_cents' => $plan['price'] * 100,
                    'currency' => 'USD',
                    'validity_days' => $plan['validity_days'],
                    'branch_scope' => 'all',
                    'perks' => $plan['perks'],
                    'is_popular' => $plan['popular'],
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }
}
