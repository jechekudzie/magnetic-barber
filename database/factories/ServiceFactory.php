<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'service_category_id' => ServiceCategory::factory(),
            'description' => fake()->sentence(10),
            'default_duration_minutes' => fake()->randomElement([20, 30, 45, 60]),
            'buffer_minutes' => 5,
            'is_active' => true,
        ];
    }

    public function skin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_skin_service' => true,
            'requires_room' => true,
            'is_house_call_eligible' => false,
        ]);
    }

    public function needingPatchTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_patch_test' => true,
            'patch_test_lead_hours' => 48,
        ]);
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
