<?php

namespace Database\Seeders;

use App\Enums\GenderTag;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The service menu, taken line for line from the printed men's and ladies'
 * price cards. Prices are per branch, so they attach on the pivot.
 *
 * Men's and ladies' work is carried as separate services rather than one
 * service with two prices, because the cards charge differently for the same
 * job: Haircut + Dye is $10 on the men's card and $15 on the ladies'. A single
 * service cannot hold both, and branch_service prices per branch, not per
 * client. The only shared line is the Deep Clean & LED Therapy Facial, which
 * is $10 on both cards and is genuinely the same treatment.
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
     * Durations are not printed on the cards. These are working estimates so
     * the diary can allocate chair time; correct them in the admin pricing
     * screen once the shop has timed a few.
     *
     * @var array<int, array{name: string, tagline: string, icon: string, description: string, services: array<int, array<string, mixed>>}>
     */
    private array $catalog = [
        [
            'name' => 'Cuts',
            'tagline' => 'Men, ladies and kids. Every cut finishes with a line up.',
            'icon' => 'scissors',
            'description' => 'The core of the shop. Clean cuts, fresh looks, confidence.',
            'services' => [
                ['name' => 'Standard Haircut', 'for' => GenderTag::Men, 'price' => 10, 'minutes' => 45, 'featured' => true, 'description' => 'Consultation, cut, line up and finish.'],
                ['name' => 'Ladies Cut', 'for' => GenderTag::Women, 'price' => 10, 'minutes' => 45, 'featured' => true, 'description' => 'Cut and shape, finished the way you wear it.'],
                ['name' => 'Kids Haircut', 'for' => GenderTag::Kids, 'price' => 10, 'minutes' => 30, 'description' => 'Under 12. Patient chair, no rush.'],
            ],
        ],
        [
            'name' => 'Colour and Texture',
            'tagline' => 'Dye, tint, curls and Xcurl, always with the cut.',
            'icon' => 'palette',
            'description' => 'Colour and texture work is booked together with the cut, as one slot.',
            'services' => [
                ['name' => 'Haircut and Dye', 'for' => GenderTag::Men, 'price' => 10, 'minutes' => 60, 'featured' => true, 'description' => 'Men. Cut and dye in one sitting.'],
                ['name' => 'Ladies Haircut and Dye', 'for' => GenderTag::Women, 'price' => 15, 'minutes' => 75, 'description' => 'Ladies. Cut and dye in one sitting.'],
                ['name' => 'Haircut and Curls', 'for' => GenderTag::Men, 'price' => 15, 'minutes' => 90, 'featured' => true, 'description' => 'Men. Cut, then curls put in and set.'],
                ['name' => 'Ladies Haircut and Xcurl', 'for' => GenderTag::Women, 'price' => 15, 'minutes' => 90, 'description' => 'Ladies. Cut with an Xcurl treatment.'],
                ['name' => 'Haircut and Tint', 'for' => GenderTag::Men, 'price' => 20, 'from' => true, 'minutes' => 90, 'description' => 'Men. From $20, depending with colour. The final price is confirmed in the chair.'],
                ['name' => 'Ladies Haircut and Tint', 'for' => GenderTag::Women, 'price' => 20, 'from' => true, 'minutes' => 105, 'description' => 'Ladies. From $20, depending with colour. The final price is confirmed in the chair.'],
            ],
        ],
        [
            'name' => 'Skin and Facials',
            'tagline' => 'Deep clean, LED therapy, mini facials and brows.',
            'icon' => 'sparkles',
            'description' => 'The Skin Room. Invest in your skin, it is going to represent you for a long time.',
            'services' => [
                ['name' => 'The Deep Clean and LED Therapy Facial', 'for' => GenderTag::Unisex, 'price' => 10, 'minutes' => 45, 'skin' => true, 'featured' => true, 'description' => 'Deep cleanse followed by LED therapy. On both price cards at the same price.'],
                ['name' => 'Haircut and Mini Facial', 'for' => GenderTag::Men, 'price' => 15, 'minutes' => 60, 'skin' => true, 'featured' => true, 'description' => 'Men. Cut plus a mini facial, booked as one slot.'],
                ['name' => 'Eyebrow Shaping and Mini Facial', 'for' => GenderTag::Women, 'price' => 10, 'minutes' => 30, 'skin' => true, 'description' => 'Ladies. Brows shaped, followed by a mini facial.'],
            ],
        ],
    ];

    public function run(): void
    {
        $branches = Branch::query()->get();
        $keptServiceIds = [];
        $keptCategoryIds = [];

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

            $keptCategoryIds[] = $category->id;

            foreach ($definition['services'] as $serviceOrder => $item) {
                /*
                 * withTrashed, because a previous run may have retired this
                 * slug. The slug column is unique across soft deleted rows
                 * too, so a plain updateOrCreate would try to insert a
                 * duplicate instead of reviving the row.
                 */
                $service = Service::withTrashed()->updateOrCreate(
                    ['slug' => Str::slug($item['name'])],
                    [
                        'deleted_at' => null,
                        'service_category_id' => $category->id,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        // Which printed card this line appears on.
                        'audience' => $item['for'],
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

                $keptServiceIds[] = $service->id;

                foreach ($branches as $branch) {
                    $branch->services()->syncWithoutDetaching([
                        $service->id => [
                            'price_cents' => (int) round($item['price'] * 100),
                            'currency' => 'USD',
                            // "From $20, depending with colour" on the card.
                            'is_from_price' => $item['from'] ?? false,
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

        $this->retireEverythingElse($keptServiceIds, $keptCategoryIds);
    }

    /**
     * The price cards are the whole menu. Anything still in the catalog that is
     * not on them is retired rather than deleted outright: appointments point
     * at services, and a past booking must still be able to name what was
     * bought. Deactivating takes it out of the booking flow; the soft delete
     * takes it out of the admin lists.
     *
     * @param  list<int>  $keptServiceIds
     * @param  list<int>  $keptCategoryIds
     */
    private function retireEverythingElse(array $keptServiceIds, array $keptCategoryIds): void
    {
        $retiring = Service::query()->whereNotIn('id', $keptServiceIds)->get();

        foreach ($retiring as $service) {
            // Off the price list and out of the booking flow at every branch.
            $service->branches()->newPivotQuery()
                ->where('service_id', $service->id)
                ->update(['is_active' => false]);

            $service->update(['is_active' => false]);
            $service->delete();
        }

        ServiceCategory::query()
            ->whereNotIn('id', $keptCategoryIds)
            ->update(['is_active' => false]);
    }
}
