<?php

namespace Database\Factories;

use App\Enums\GenderTag;
use App\Models\Style;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Style>
 */
class StyleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => fake()->unique()->numerify('##'),
            'description' => fake()->sentence(10),
            'gender_tag' => fake()->randomElement(GenderTag::cases()),
            'hair_type_tag' => ['coily'],
            'typical_duration_minutes' => 45,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
