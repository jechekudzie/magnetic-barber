<?php

namespace Database\Factories;

use App\Enums\ClientSource;
use App\Models\Branch;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClientProfile>
 */
class ClientProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'account_number' => 'MB-'.fake()->unique()->numerify('####'),
            'home_branch_id' => Branch::factory(),
            'referral_code' => Str::upper(fake()->unique()->lexify('??????')),
            'source' => fake()->randomElement(ClientSource::cases()),
            'whatsapp_opt_in' => true,
            'marketing_opt_in' => false,
        ];
    }

    public function marketingOptedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'marketing_opt_in' => true,
            'marketing_opt_in_at' => now(),
        ]);
    }
}
