<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $displayName = fake()->unique()->firstName();

        return [
            'user_id' => User::factory(),
            'display_name' => $displayName,
            'slug' => Str::slug($displayName).'-'.fake()->unique()->randomNumber(4),
            'title' => fake()->randomElement(['Master Barber', 'Senior Barber', 'Barber', 'Aesthetician']),
            'bio' => fake()->sentence(14),
            'specialities' => ['Fades', 'Beard sculpting'],
            'accepts_house_calls' => fake()->boolean(),
            'is_bookable' => true,
            'show_on_site' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_on_site' => false,
        ]);
    }
}
