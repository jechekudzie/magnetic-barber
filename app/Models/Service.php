<?php

namespace App\Models;

use App\Concerns\HasCatalogSlug;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property int $service_category_id
 * @property string $name
 * @property string|null $description
 * @property int $default_duration_minutes
 * @property int $buffer_minutes
 * @property bool $requires_patch_test
 * @property int|null $patch_test_lead_hours
 * @property bool $is_skin_service
 * @property bool $is_house_call_eligible
 * @property bool $requires_room
 * @property bool $is_featured
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['slug', 'service_category_id', 'name', 'description', 'default_duration_minutes', 'buffer_minutes', 'requires_patch_test', 'patch_test_lead_hours', 'is_skin_service', 'is_house_call_eligible', 'requires_room', 'is_featured', 'sort_order', 'is_active'])]
class Service extends Model
{
    use HasCatalogSlug;

    /** @use HasFactory<ServiceFactory> */
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
            'requires_patch_test' => 'boolean',
            'is_skin_service' => 'boolean',
            'is_house_call_eligible' => 'boolean',
            'requires_room' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['price_cents', 'currency', 'duration_minutes', 'house_call_surcharge_cents', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<Style, $this> */
    public function styles(): HasMany
    {
        return $this->hasMany(Style::class);
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
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Only meaningful when the model was loaded through a branch, because price
     * is a property of the branch and service together, never of the service.
     */
    public function priceForLoadedBranch(): ?Money
    {
        $pivot = $this->branchPivot();
        $cents = $pivot?->getAttribute('price_cents');

        if ($cents === null) {
            return null;
        }

        return Money::ofCents((int) $cents, $pivot->getAttribute('currency'));
    }

    public function durationForLoadedBranch(): int
    {
        $duration = $this->branchPivot()?->getAttribute('duration_minutes');

        return (int) ($duration ?? $this->default_duration_minutes);
    }

    /**
     * The pivot arrives one of two ways: on the model itself when it was read
     * through $branch->services, or on the single eager loaded branch when it
     * was read through Service::with('branches'). Both are used.
     */
    private function branchPivot(): ?Pivot
    {
        $own = $this->getAttribute('pivot');

        if ($own instanceof Pivot && $own->getAttribute('price_cents') !== null) {
            return $own;
        }

        if ($this->relationLoaded('branches')) {
            $pivot = $this->branches->first()?->getAttribute('pivot');

            return $pivot instanceof Pivot ? $pivot : null;
        }

        return null;
    }
}
