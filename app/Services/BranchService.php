<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the website and the mobile app need to know about where the shop
 * is and when it is open.
 */
final class BranchService
{
    /**
     * @return Collection<int, Branch>
     */
    public function all(): Collection
    {
        return Branch::query()->active()->ordered()->get();
    }

    public function findBySlug(string $slug): ?Branch
    {
        return $this->all()->firstWhere('slug', $slug);
    }

    /**
     * The branch a visitor sees when they have not chosen one yet. Prices are
     * per branch, so something always has to be selected.
     */
    public function default(): ?Branch
    {
        return $this->all()->first();
    }

    /**
     * Resolves a requested slug, falling back to the default rather than 404ing
     * a price list because someone kept an old link.
     */
    public function resolve(?string $slug): ?Branch
    {
        if ($slug === null) {
            return $this->default();
        }

        return $this->findBySlug($slug) ?? $this->default();
    }
}
