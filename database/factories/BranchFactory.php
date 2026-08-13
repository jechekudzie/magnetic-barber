<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'code' => Str::upper(fake()->unique()->lexify('??')),
            'tagline' => fake()->sentence(4),
            'phone' => '+2637'.fake()->numerify('########'),
            'whatsapp' => '+2637'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'address_line' => fake()->streetAddress(),
            'area' => fake()->city(),
            'city' => 'Harare',
            // Explicit: a database default never reaches the in-memory model,
            // which quietly makes a factory branch behave as UTC.
            'timezone' => 'Africa/Harare',
            'latitude' => fake()->latitude(-18, -17),
            'longitude' => fake()->longitude(30, 32),
            'opens_at' => '08:00',
            'closes_at' => '19:00',
            'days_open' => [1, 2, 3, 4, 5, 6],
            'chair_count' => fake()->numberBetween(4, 10),
            'house_call_enabled' => false,
            'is_active' => true,
        ];
    }

    public function houseCalls(): static
    {
        return $this->state(fn (array $attributes) => [
            'house_call_enabled' => true,
            'house_call_radius_km' => 25,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
