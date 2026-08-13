<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;

/**
 * Live Eloquent reads. Caching belongs on the shaped payload in CatalogPayload,
 * never on a model collection.
 */
final class ReviewService
{
    /**
     * @return Collection<int, Review>
     */
    public function published(int $limit = 9): Collection
    {
        return Review::query()
            ->published()
            ->latest('published_at')
            ->with('branch:id,name,slug')
            ->limit($limit)
            ->get();
    }

    public function averageRating(): ?float
    {
        $average = Review::query()->published()->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    public function publishedCount(): int
    {
        return Review::query()->published()->count();
    }
}
