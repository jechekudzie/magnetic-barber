<?php

namespace App\Services;

use App\Models\Style;
use Illuminate\Database\Eloquent\Collection;

/**
 * The style gallery. Every style has a spoken code so a client can ask for
 * "number 03" and any barber cuts the same thing.
 */
final class StyleService
{
    /**
     * @return Collection<int, Style>
     */
    public function gallery(?string $gender = null, ?string $hairType = null): Collection
    {
        $styles = Style::query()->active()->ordered()->with('service')->get();

        if ($gender !== null && $gender !== 'all') {
            $styles = $styles->filter(
                fn (Style $style): bool => in_array($style->gender_tag?->value, [$gender, 'unisex'], true)
            );
        }

        if ($hairType !== null && $hairType !== 'all') {
            $styles = $styles->filter(
                fn (Style $style): bool => in_array($hairType, $style->hair_type_tag ?? [], true)
            );
        }

        return $styles->values();
    }

    /**
     * @return Collection<int, Style>
     */
    public function featured(int $limit = 6): Collection
    {
        return $this->gallery()
            ->filter(fn (Style $style): bool => $style->is_featured)
            ->take($limit)
            ->values();
    }

    public function findBySlug(string $slug): ?Style
    {
        return $this->gallery()->firstWhere('slug', $slug);
    }

    /**
     * The filter options the gallery UI renders, derived from what is actually
     * in the gallery rather than hardcoded in two places.
     *
     * @return array{genders: array<int, string>, hairTypes: array<int, string>}
     */
    public function filters(): array
    {
        $styles = $this->gallery();

        return [
            'genders' => $styles
                ->pluck('gender_tag')
                ->filter()
                ->map(fn ($tag): string => $tag->value)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'hairTypes' => $styles
                ->pluck('hair_type_tag')
                ->filter()
                ->flatten()
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
