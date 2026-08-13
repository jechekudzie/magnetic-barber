<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'reference' => 'MB-A'.fake()->unique()->regexify('[0-9A-HJKMNP-TV-Z]{5}'),
            'branch_id' => Branch::factory(),
            'client_id' => User::factory()->client(),
            'staff_id' => User::factory(),
            'type' => AppointmentType::Scheduled,
            'status' => AppointmentStatus::Confirmed,
            'source' => 'web',
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addMinutes(45),
            'duration_minutes' => 45,
            'subtotal_cents' => 1200,
            'total_cents' => 1200,
            'currency' => 'USD',
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function at(CarbonInterface $start, int $minutes = 45): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addMinutes($minutes),
            'duration_minutes' => $minutes,
        ]);
    }
}
