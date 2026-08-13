<?php

namespace App\Services;

use App\Http\Resources\BranchResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StaffResource;
use App\Http\Resources\StyleResource;
use App\Models\Branch;
use App\Support\Money;
use App\Support\ResourcePayload;

/**
 * One shaped, cached payload per thing the public can read, consumed by both
 * the website and the mobile app. If it is not returned from here, the two
 * clients are looking at different data.
 *
 * Everything on this class returns plain arrays. That is deliberate: Eloquent
 * models cannot be cached (Laravel 13 blocks unserializing them), and plain
 * arrays are exactly what Inertia and the JSON API both want anyway.
 */
final class CatalogPayload
{
    public function __construct(
        private readonly CatalogCache $cache,
        private readonly BranchService $branches,
        private readonly CatalogService $catalog,
        private readonly StyleService $styles,
        private readonly PlanService $plans,
        private readonly TeamService $team,
        private readonly ReviewService $reviews,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function branches(): array
    {
        return $this->cache->remember(
            'payload:branches',
            fn (): array => ResourcePayload::flatten(BranchResource::collection($this->branches->all()))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function branch(Branch $branch): array
    {
        return $this->cache->remember(
            "payload:branch:{$branch->id}",
            fn (): array => ResourcePayload::flatten(new BranchResource($branch))
        );
    }

    /**
     * The full price list for one branch, grouped by category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function priceList(Branch $branch): array
    {
        return $this->cache->remember(
            "payload:pricelist:{$branch->id}",
            fn (): array => ResourcePayload::flatten(ServiceCategoryResource::collection(
                $this->catalog->priceListFor($branch)
            ))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function featuredServices(Branch $branch, int $limit = 6): array
    {
        return $this->cache->remember(
            "payload:featured:{$branch->id}:{$limit}",
            fn (): array => ResourcePayload::flatten(ServiceResource::collection(
                $this->catalog->featuredFor($branch, $limit)
            ))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function servicesInCategory(Branch $branch, string $categorySlug): array
    {
        return $this->cache->remember(
            "payload:category:{$categorySlug}:{$branch->id}",
            fn (): array => ResourcePayload::flatten(ServiceResource::collection(
                $this->catalog->servicesInCategory($branch, $categorySlug)
            ))
        );
    }

    /**
     * Filtering happens after the cache read: the whole gallery is a dozen
     * rows, so caching one copy and filtering in memory beats a key per combo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(?string $gender = null, ?string $hairType = null): array
    {
        $all = $this->cache->remember(
            'payload:styles',
            fn (): array => ResourcePayload::flatten(StyleResource::collection($this->styles->gallery()))
        );

        return array_values(array_filter($all, function (array $style) use ($gender, $hairType): bool {
            if ($gender !== null && $gender !== 'all'
                && ! in_array($style['gender_tag'], [$gender, 'unisex'], true)) {
                return false;
            }

            if ($hairType !== null && $hairType !== 'all'
                && ! in_array($hairType, $style['hair_type_tag'], true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * One style, with the price of the service it is booked as filled in from
     * the branch the visitor is looking at. The gallery itself is cached
     * branch-agnostically, so without this a style page shows no price at all.
     *
     * Accepts either the slug (used in URLs) or the ULID (used by the booking
     * wizard), so both routes into a style get the same priced payload.
     *
     * @return array<string, mixed>|null
     */
    public function style(string $identifier, ?Branch $branch): ?array
    {
        $gallery = collect($this->styles());

        $style = $gallery->firstWhere('slug', $identifier)
            ?? $gallery->firstWhere('id', $identifier);

        if ($style === null) {
            return null;
        }

        $serviceSlug = $style['service']['slug'] ?? null;

        if ($branch === null || $serviceSlug === null) {
            return $style;
        }

        $priced = collect($this->priceList($branch))
            ->flatMap(fn (array $category): array => $category['services'] ?? [])
            ->firstWhere('slug', $serviceSlug);

        if ($priced !== null) {
            $style['service'] = $priced;
        }

        return $style;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function featuredStyles(int $limit = 6): array
    {
        return array_slice(
            array_values(array_filter($this->styles(), fn (array $style): bool => $style['is_featured'])),
            0,
            $limit,
        );
    }

    /**
     * @return array{genders: array<int, string>, hairTypes: array<int, string>}
     */
    public function styleFilters(): array
    {
        return $this->cache->remember(
            'payload:style-filters',
            fn (): array => $this->styles->filters()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        return $this->cache->remember(
            'payload:plans',
            fn (): array => ResourcePayload::flatten(PlanResource::collection($this->plans->all()))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function team(?Branch $branch = null): array
    {
        $key = $branch === null ? 'payload:team' : "payload:team:{$branch->id}";

        return $this->cache->remember(
            $key,
            fn (): array => ResourcePayload::flatten(StaffResource::collection($this->team->onSite($branch)))
        );
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, average_rating: float|null, total: int}
     */
    public function reviews(int $limit = 9): array
    {
        return $this->cache->remember("payload:reviews:{$limit}", fn (): array => [
            'data' => ResourcePayload::flatten(ReviewResource::collection($this->reviews->published($limit))),
            'average_rating' => $this->reviews->averageRating(),
            'total' => $this->reviews->publishedCount(),
        ]);
    }

    /**
     * The "from $7" figure on the homepage hero.
     *
     * @return array<string, mixed>|null
     */
    public function cheapestPrice(Branch $branch): ?array
    {
        $cents = $this->cache->remember(
            "payload:cheapest:{$branch->id}",
            fn (): ?int => $this->catalog->cheapestPriceCentsFor($branch)
        );

        return $cents === null
            ? null
            : Money::ofCents($cents, config('magnetic.default_currency'))->toArray();
    }
}
