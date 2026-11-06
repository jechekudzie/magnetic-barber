<?php

namespace Database\Seeders;

use App\Enums\GenderTag;
use App\Models\Service;
use App\Models\Style;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The numbered gallery from the proposal. The code is the point: a client says
 * "number 03" over WhatsApp and every barber cuts the same thing.
 *
 * Codes are stable once published, so retiring a look leaves a gap in the
 * sequence rather than renumbering the ones after it. Code 11 (Beard Sculpt)
 * was retired when beard work came off the price cards.
 */
class StyleSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $styles = [
        ['code' => '01', 'name' => 'Low Fade', 'service' => 'standard-haircut', 'gender' => GenderTag::Men, 'hair' => ['coily', 'curly'], 'minutes' => 45, 'featured' => true, 'description' => 'Fade starts low above the ear and blends up soft. Safe and sharp.'],
        ['code' => '02', 'name' => 'Bald Fade', 'service' => 'standard-haircut', 'gender' => GenderTag::Men, 'hair' => ['coily', 'curly', 'straight'], 'minutes' => 50, 'featured' => true, 'description' => 'Taken to the skin at the base, no line where the blend starts.'],
        ['code' => '03', 'name' => 'Taper', 'service' => 'standard-haircut', 'gender' => GenderTag::Men, 'hair' => ['coily', 'wavy'], 'minutes' => 40, 'featured' => true, 'description' => 'Clean around the ears and the neckline, length kept on top.'],
        ['code' => '04', 'name' => 'Line Up', 'service' => 'standard-haircut', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'curly'], 'minutes' => 20, 'featured' => true, 'description' => 'Edges squared off. The one that keeps last week\'s cut looking new.'],
        ['code' => '05', 'name' => 'Afro Shape', 'service' => 'standard-haircut', 'gender' => GenderTag::Unisex, 'hair' => ['coily'], 'minutes' => 45, 'featured' => true, 'description' => 'Shaped round and even, picked out, edges defined.'],
        ['code' => '06', 'name' => 'Dread Retwist', 'service' => 'standard-haircut', 'gender' => GenderTag::Unisex, 'hair' => ['locs'], 'minutes' => 90, 'featured' => true, 'description' => 'Roots retwisted and set. Book the longer slot.'],
        ['code' => '07', 'name' => 'Waves', 'service' => 'standard-haircut', 'gender' => GenderTag::Men, 'hair' => ['coily'], 'minutes' => 40, 'description' => 'Cut low and brushed to hold the pattern.'],
        ['code' => '08', 'name' => 'Mohawk', 'service' => 'standard-haircut', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'curly'], 'minutes' => 50, 'description' => 'Strip left through the centre, sides faded down.'],
        ['code' => '09', 'name' => 'Buzz Cut', 'service' => 'standard-haircut', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'straight', 'wavy'], 'minutes' => 25, 'description' => 'One guard all over. Fast, and it suits more people than they think.'],
        ['code' => '10', 'name' => 'Kids Fade', 'service' => 'kids-haircut', 'gender' => GenderTag::Kids, 'hair' => ['coily', 'curly'], 'minutes' => 30, 'description' => 'A proper fade, scaled down, in a chair that does not rush.'],
        ['code' => '12', 'name' => 'Twist Out', 'service' => 'ladies-cut', 'gender' => GenderTag::Women, 'hair' => ['coily', 'curly'], 'minutes' => 75, 'description' => 'Twisted, set and separated for definition.'],
    ];

    public function run(): void
    {
        $services = Service::pluck('id', 'slug');

        foreach ($this->styles as $order => $style) {
            Style::updateOrCreate(
                ['slug' => Str::slug($style['name'])],
                [
                    'code' => $style['code'],
                    'name' => $style['name'],
                    'description' => $style['description'],
                    'service_id' => $services[$style['service']] ?? null,
                    'gender_tag' => $style['gender'],
                    'hair_type_tag' => $style['hair'],
                    'typical_duration_minutes' => $style['minutes'],
                    'is_featured' => $style['featured'] ?? false,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }
}
