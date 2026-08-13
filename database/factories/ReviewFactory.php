<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'author_name' => fake()->firstName().' '.Str::substr(fake()->lastName(), 0, 1).'.',
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->sentence(14),
            'is_public' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'published_at' => now(),
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'flagged_at' => now(),
        ]);
    }
}
