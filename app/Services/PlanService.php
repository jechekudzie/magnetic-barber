<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

final class PlanService
{
    /**
     * @return Collection<int, Plan>
     */
    public function all(): Collection
    {
        return Plan::query()->active()->ordered()->get();
    }

    public function findBySlug(string $slug): ?Plan
    {
        return $this->all()->firstWhere('slug', $slug);
    }
}
