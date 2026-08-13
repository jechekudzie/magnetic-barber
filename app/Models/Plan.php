<?php

namespace App\Models;

use App\Concerns\HasCatalogSlug;
use App\Enums\PlanType;
use App\Support\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property string|null $description
 * @property PlanType $type
 * @property array<int, int>|null $included_service_ids
 * @property int|null $session_count
 * @property int $price_cents
 * @property string $currency
 * @property int $validity_days
 * @property string $branch_scope
 * @property array<int, string>|null $perks
 * @property bool $is_popular
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['slug', 'name', 'tagline', 'description', 'type', 'included_service_ids', 'session_count', 'price_cents', 'currency', 'validity_days', 'branch_scope', 'perks', 'is_popular', 'sort_order', 'is_active'])]
class Plan extends Model
{
    use HasCatalogSlug;

    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasUlids, SoftDeletes;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'included_service_ids' => 'array',
            'perks' => 'array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function price(): Money
    {
        return Money::ofCents($this->price_cents, $this->currency);
    }

    /**
     * @return Collection<int, Service>
     */
    public function includedServices(): Collection
    {
        return Service::query()
            ->whereIn('id', $this->included_service_ids ?? [])
            ->ordered()
            ->get();
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price_cents');
    }
}
