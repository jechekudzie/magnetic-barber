<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The price list. Price and duration are properties of a branch and a service
 * together, so every read here is scoped to one branch.
 *
 * These return live Eloquent results. Caching happens one layer up on the
 * shaped payload, because Eloquent models cannot round trip through the cache.
 */
final class CatalogService
{
    /**
     * Categories with the services that branch actually sells, each carrying
     * that branch's price on the pivot.
     *
     * @return Collection<int, ServiceCategory>
     */
    public function priceListFor(Branch $branch): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->with([
                'services' => fn ($query) => $query
                    ->active()
                    ->ordered()
                    ->whereHas('branches', fn ($q) => $q
                        ->whereKey($branch->id)
                        ->where('branch_service.is_active', true))
                    ->with(['branches' => fn ($q) => $q->whereKey($branch->id)]),
            ])
            ->get()
            // A category whose services are all unavailable at this branch
            // should not render as an empty heading.
            ->reject(fn (ServiceCategory $category): bool => $category->services->isEmpty())
            ->values();
    }

    /**
     * @return Collection<int, ServiceCategory>
     */
    public function categories(): Collection
    {
        return ServiceCategory::query()->active()->ordered()->get();
    }

    /**
     * @return Collection<int, Service>
     */
    public function featuredFor(Branch $branch, int $limit = 6): Collection
    {
        return $this->scopedToBranch($branch)
            ->where('is_featured', true)
            ->limit($limit)
            ->get();
    }

    /**
     * Services in one category at one branch, used by the skin room page.
     *
     * @return Collection<int, Service>
     */
    public function servicesInCategory(Branch $branch, string $categorySlug): Collection
    {
        return $this->scopedToBranch($branch)
            ->whereHas('category', fn ($q) => $q->where('slug', $categorySlug))
            ->get();
    }

    /**
     * The cheapest service at a branch, for "from $7" copy on the site.
     */
    /**
     * The cheapest actual cut, for the "Cuts from $10" line on the hero.
     *
     * Scoped to the cuts category on purpose: the site says "cuts from", so
     * picking up a cheaper wash or scalp massage would make that copy a lie.
     */
    public function cheapestPriceCentsFor(Branch $branch, string $categorySlug = 'cuts-and-beards'): ?int
    {
        $query = fn (?string $slug) => $branch->services()
            ->wherePivot('is_active', true)
            ->where('branch_service.price_cents', '>', 0)
            ->when($slug !== null, fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $slug)
            ))
            ->orderBy('branch_service.price_cents')
            ->first();

        // Fall back to the whole menu if this branch sells no cuts at all.
        $cheapest = $query($categorySlug) ?? $query(null);

        return $cheapest?->priceForLoadedBranch()?->cents;
    }

    /**
     * @return Builder<Service>
     */
    private function scopedToBranch(Branch $branch): Builder
    {
        return Service::query()
            ->active()
            ->ordered()
            ->whereHas('branches', fn ($q) => $q
                ->whereKey($branch->id)
                ->where('branch_service.is_active', true))
            ->with(['category', 'branches' => fn ($q) => $q->whereKey($branch->id)]);
    }
}
