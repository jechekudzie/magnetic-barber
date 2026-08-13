<?php

namespace App\Models;

use App\Concerns\HasCatalogSlug;
use App\Enums\GenderTag;
use Database\Factories\StyleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $service_id
 * @property GenderTag|null $gender_tag
 * @property array<int, string>|null $hair_type_tag
 * @property int|null $typical_duration_minutes
 * @property string|null $image_path
 * @property bool $is_featured
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['slug', 'code', 'name', 'description', 'service_id', 'gender_tag', 'hair_type_tag', 'typical_duration_minutes', 'image_path', 'is_featured', 'sort_order', 'is_active'])]
class Style extends Model
{
    use HasCatalogSlug;

    /** @use HasFactory<StyleFactory> */
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
            'gender_tag' => GenderTag::class,
            'hair_type_tag' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
        $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forGender(Builder $query, ?string $gender): void
    {
        $query->when($gender !== null && $gender !== 'all', function (Builder $query) use ($gender) {
            $query->whereIn('gender_tag', [$gender, GenderTag::Unisex->value]);
        });
    }
}
