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
 */
class StyleSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $styles = [
        ['code' => '01', 'name' => 'Low Fade', 'service' => 'signature-cut', 'gender' => GenderTag::Men, 'hair' => ['coily', 'curly'], 'minutes' => 45, 'featured' => true, 'description' => 'Fade starts low above the ear and blends up soft. Safe and sharp.'],
        ['code' => '02', 'name' => 'Bald Fade', 'service' => 'skin-fade', 'gender' => GenderTag::Men, 'hair' => ['coily', 'curly', 'straight'], 'minutes' => 50, 'featured' => true, 'description' => 'Taken to the skin at the base, no line where the blend starts.'],
        ['code' => '03', 'name' => 'Taper', 'service' => 'signature-cut', 'gender' => GenderTag::Men, 'hair' => ['coily', 'wavy'], 'minutes' => 40, 'featured' => true, 'description' => 'Clean around the ears and the neckline, length kept on top.'],
        ['code' => '04', 'name' => 'Line Up', 'service' => 'shape-up', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'curly'], 'minutes' => 20, 'featured' => true, 'description' => 'Edges squared off. The one that keeps last week\'s cut looking new.'],
        ['code' => '05', 'name' => 'Afro Shape', 'service' => 'signature-cut', 'gender' => GenderTag::Unisex, 'hair' => ['coily'], 'minutes' => 45, 'featured' => true, 'description' => 'Shaped round and even, picked out, edges defined.'],
        ['code' => '06', 'name' => 'Dread Retwist', 'service' => 'signature-cut', 'gender' => GenderTag::Unisex, 'hair' => ['locs'], 'minutes' => 90, 'featured' => true, 'description' => 'Roots retwisted and set. Book the longer slot.'],
        ['code' => '07', 'name' => 'Waves', 'service' => 'signature-cut', 'gender' => GenderTag::Men, 'hair' => ['coily'], 'minutes' => 40, 'description' => 'Cut low and brushed to hold the pattern.'],
        ['code' => '08', 'name' => 'Mohawk', 'service' => 'skin-fade', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'curly'], 'minutes' => 50, 'description' => 'Strip left through the centre, sides faded down.'],
        ['code' => '09', 'name' => 'Buzz Cut', 'service' => 'signature-cut', 'gender' => GenderTag::Unisex, 'hair' => ['coily', 'straight', 'wavy'], 'minutes' => 25, 'description' => 'One guard all over. Fast, and it suits more people than they think.'],
        ['code' => '10', 'name' => 'Kids Fade', 'service' => 'kids-cut', 'gender' => GenderTag::Kids, 'hair' => ['coily', 'curly'], 'minutes' => 30, 'description' => 'A proper fade, scaled down, in a chair that does not rush.'],
        ['code' => '11', 'name' => 'Beard Sculpt', 'service' => 'beard-trim-and-shape', 'gender' => GenderTag::Men, 'hair' => ['coily', 'straight'], 'minutes' => 30, 'description' => 'Cheek line and neckline set, length shaped to the jaw.'],
        ['code' => '12', 'name' => 'Twist Out', 'service' => 'signature-cut', 'gender' => GenderTag::Women, 'hair' => ['coily', 'curly'], 'minutes' => 75, 'description' => 'Twisted, set and separated for definition.'],
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
