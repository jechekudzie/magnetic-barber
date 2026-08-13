<?php

namespace Database\Factories;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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
            'tagline' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'type' => PlanType::SessionPack,
            'session_count' => 4,
            'price_cents' => fake()->numberBetween(2000, 20000),
            'currency' => 'USD',
            'validity_days' => 30,
            'is_active' => true,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PlanType::Unlimited,
            'session_count' => null,
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_popular' => true,
        ]);
    }
}
