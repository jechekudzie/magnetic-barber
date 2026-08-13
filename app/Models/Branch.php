<?php

namespace App\Models;

use App\Concerns\HasCatalogSlug;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $tagline
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $address_line
 * @property string|null $area
 * @property string $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $directions_note
 * @property string $timezone
 * @property string $opens_at
 * @property string $closes_at
 * @property array<int, int>|null $days_open
 * @property int $chair_count
 * @property bool $house_call_enabled
 * @property int|null $house_call_radius_km
 * @property int $house_call_fee_cents
 * @property string|null $house_call_opens_at
 * @property string|null $house_call_closes_at
 * @property array<int, int>|null $house_call_days_open
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['slug', 'code', 'name', 'tagline', 'phone', 'whatsapp', 'email', 'address_line', 'area', 'city', 'latitude', 'longitude', 'directions_note', 'timezone', 'opens_at', 'closes_at', 'days_open', 'chair_count', 'house_call_enabled', 'house_call_radius_km', 'house_call_fee_cents',
    'house_call_opens_at', 'house_call_closes_at', 'house_call_days_open',
    'sort_order', 'is_active'])]
class Branch extends Model
{
    use HasCatalogSlug;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasUlids, SoftDeletes;

    /**
     * The ULID lives beside the auto increment id rather than replacing it.
     */
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
            'days_open' => 'array',
            'house_call_days_open' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'house_call_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasOne<BranchSequence, $this> */
    public function sequence(): HasOne
    {
        return $this->hasOne(BranchSequence::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['employment_type', 'commission_rate', 'chair_rate_cents', 'currency', 'is_primary', 'starts_on', 'ends_on'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(['price_cents', 'currency', 'duration_minutes', 'house_call_surcharge_cents', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<ClientProfile, $this> */
    public function clientProfiles(): HasMany
    {
        return $this->hasMany(ClientProfile::class, 'home_branch_id');
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
     * Weekday numbers match Carbon: 0 is Sunday.
     */
    public function isOpenOn(int $weekday): bool
    {
        return in_array($weekday, $this->days_open ?? [], true);
    }

    /**
     * House calls can run a narrower window than the shop floor, because the
     * barber has to travel there and back. Unset means "same as the shop".
     */
    public function isOpenForHouseCallsOn(int $weekday): bool
    {
        $days = $this->house_call_days_open;

        if ($days === null || $days === []) {
            return $this->isOpenOn($weekday);
        }

        return in_array($weekday, $days, true);
    }

    public function houseCallOpensAt(): string
    {
        return $this->house_call_opens_at ?? $this->opens_at;
    }

    public function houseCallClosesAt(): string
    {
        return $this->house_call_closes_at ?? $this->closes_at;
    }

    public function houseCallFee(): Money
    {
        return Money::ofCents(
            $this->house_call_fee_cents,
            config('magnetic.default_currency'),
        );
    }

    public function mapUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
    }
}
