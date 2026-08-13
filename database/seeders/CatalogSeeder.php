<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The service menu, with an opening price list for the Avenues branch. Prices
 * are per branch, so they attach on the pivot.
 *
 * There is deliberately no "House Calls" category: the client chooses house
 * call or shop at the start of the booking, and any service flagged
 * is_house_call_eligible can be delivered either way. Duplicating the cuts as
 * separate house call products would mean maintaining two prices for one job.
 */
class CatalogSeeder extends Seeder
{
    /**
     * Categories, then their services. price is USD and lands on branch_service.
     *
     * @var array<int, array{name: string, tagline: string, icon: string, description: string, services: array<int, array<string, mixed>>}>
     */
    private array $catalog = [
        [
            'name' => 'Cuts and Beards',
            'tagline' => 'Fades, shape ups, beard trims, hot towel shaves, kids cuts.',
            'icon' => 'scissors',
            'description' => 'The core of the shop. Every cut finishes with a line up and a hot towel.',
            'services' => [
                ['name' => 'Signature Cut', 'price' => 12, 'minutes' => 45, 'featured' => true, 'description' => 'Consultation, cut, line up, hot towel finish.'],
                ['name' => 'Skin Fade', 'price' => 15, 'minutes' => 50, 'featured' => true, 'description' => 'Bald fade taken down to the skin, blended clean.'],
                ['name' => 'Shape Up', 'price' => 10, 'minutes' => 20, 'description' => 'Line up and edge work between cuts.'],
                ['name' => 'Beard Trim and Shape', 'price' => 10, 'minutes' => 30, 'featured' => true, 'description' => 'Shaped, trimmed and conditioned with oil.'],
                ['name' => 'Hot Towel Shave', 'price' => 12, 'minutes' => 40, 'description' => 'Full traditional shave, hot towels, cold finish.'],
                ['name' => 'Kids Cut', 'price' => 10, 'minutes' => 30, 'description' => 'Under 12. Patient chair, no rush.'],
                ['name' => 'Cut and Beard Combo', 'price' => 18, 'minutes' => 60, 'featured' => true, 'description' => 'Signature cut plus a full beard shape.'],
            ],
        ],
        [
            'name' => 'Wash, Steam and Dry',
            'tagline' => 'Shampoo, steam treatment, blow dry, scalp massage.',
            'icon' => 'droplets',
            'description' => 'Add to any cut, or book on its own when the scalp needs the attention.',
            'services' => [
                ['name' => 'Wash and Blow Dry', 'price' => 10, 'minutes' => 25, 'description' => 'Shampoo, condition, blow dry.'],
                ['name' => 'Steam Treatment', 'price' => 10, 'minutes' => 30, 'description' => 'Steam, deep condition, scalp massage.'],
                ['name' => 'Scalp Massage', 'price' => 10, 'minutes' => 20, 'description' => 'Fifteen minutes of pressure point work with oil.'],
            ],
        ],
        [
            'name' => 'Colour and Tinting',
            'tagline' => 'Beard tint, grey blending, colour with patch test noted.',
            'icon' => 'palette',
            'description' => 'Colour is booked with a patch test 48 hours ahead. The system will not let you book inside that window.',
            'services' => [
                ['name' => 'Beard Tint', 'price' => 10, 'minutes' => 30, 'patch_test' => true, 'description' => 'Even out the beard, keep it natural.'],
                ['name' => 'Grey Blending', 'price' => 15, 'minutes' => 45, 'patch_test' => true, 'description' => 'Softens the grey rather than covering it flat.'],
                ['name' => 'Full Colour', 'price' => 25, 'minutes' => 90, 'patch_test' => true, 'description' => 'Full head colour. Formula saved to your record for next time.'],
            ],
        ],
        [
            'name' => 'Skin and Facials',
            'tagline' => 'Cleanse, exfoliate, steam, mask, moisturise.',
            'icon' => 'sparkles',
            'description' => 'The Skin Room. Invest in your skin, it is going to represent you for a long time.',
            'services' => [
                ['name' => 'Express Facial', 'price' => 15, 'minutes' => 30, 'skin' => true, 'featured' => true, 'description' => 'Cleanse, exfoliate, moisturise. Fits beside a cut.'],
                ['name' => 'Deep Cleanse Facial', 'price' => 25, 'minutes' => 60, 'skin' => true, 'featured' => true, 'description' => 'Full five step treatment with steam and extraction.'],
                ['name' => 'Ingrown and Razor Bump Treatment', 'price' => 20, 'minutes' => 45, 'skin' => true, 'description' => 'Targeted work on bumps along the jaw and neck.'],
                ['name' => 'Cut and Facial', 'price' => 32, 'minutes' => 90, 'skin' => true, 'featured' => true, 'description' => 'Signature cut plus an express facial, booked as one slot.'],
            ],
        ],
        [
            'name' => 'Products and Accessories',
            'tagline' => 'Oils, sprays, brushes, durags, aftercare kits.',
            'icon' => 'shopping-bag',
            'description' => 'What we use in the chair, available to take home.',
            'services' => [
                ['name' => 'Aftercare Consultation', 'price' => 0, 'minutes' => 10, 'description' => 'Free. What to use, how often, and what to stop using.'],
            ],
        ],
    ];

    /**
     * Borrowdale charges a little more than the Avenues. That is the point of
     * per branch pricing, so the seed data reflects it rather than making
     * every branch identical and hiding a bug.
     *
     * @var array<string, float>
     */
    private array $branchMultipliers = [
        'harare-avenues' => 1.0,
        'borrowdale' => 1.25,
    ];

    public function run(): void
    {
        $branches = Branch::query()->get();

        foreach ($this->catalog as $categoryOrder => $definition) {
            $category = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [
                    'name' => $definition['name'],
                    'tagline' => $definition['tagline'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'sort_order' => $categoryOrder,
                    'is_active' => true,
                ],
            );

            foreach ($definition['services'] as $serviceOrder => $item) {
                $service = Service::updateOrCreate(
                    ['slug' => Str::slug($item['name'])],
                    [
                        'service_category_id' => $category->id,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'default_duration_minutes' => $item['minutes'],
                        'buffer_minutes' => 5,
                        'requires_patch_test' => $item['patch_test'] ?? false,
                        'patch_test_lead_hours' => ($item['patch_test'] ?? false) ? 48 : null,
                        'is_skin_service' => $item['skin'] ?? false,
                        'requires_room' => $item['skin'] ?? false,
                        'is_house_call_eligible' => ! ($item['skin'] ?? false),
                        'is_featured' => $item['featured'] ?? false,
                        'sort_order' => $serviceOrder,
                        'is_active' => true,
                    ],
                );

                foreach ($branches as $branch) {
                    $multiplier = $this->branchMultipliers[$branch->slug] ?? 1.0;

                    // Rounded to the nearest 50c: nobody prices a cut at $15.63.
                    $price = round(($item['price'] * $multiplier) * 2) / 2;

                    $branch->services()->syncWithoutDetaching([
                        $service->id => [
                            'price_cents' => (int) round($price * 100),
                            'currency' => 'USD',
                            'duration_minutes' => $item['minutes'],
                            'house_call_surcharge_cents' => isset($item['surcharge'])
                                ? (int) round($item['surcharge'] * 100)
                                : null,
                            'is_active' => true,
                        ],
                    ]);
                }
            }
        }
    }
}
