<?php

namespace App\Models;

use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string|null $display_name
 * @property string|null $title
 * @property string|null $bio
 * @property array<int, string>|null $specialities
 * @property string|null $instagram_handle
 * @property string|null $photo_path
 * @property bool $accepts_house_calls
 * @property bool $is_bookable
 * @property bool $show_on_site
 * @property float|null $rating_avg
 * @property int $rating_count
 * @property int $sort_order
 */
#[Fillable(['user_id', 'slug', 'display_name', 'title', 'bio', 'specialities', 'instagram_handle', 'photo_path', 'accepts_house_calls', 'is_bookable', 'show_on_site', 'sort_order'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory;

    use HasSlug;

    protected function casts(): array
    {
        return [
            'specialities' => 'array',
            'accepts_house_calls' => 'boolean',
            'is_bookable' => 'boolean',
            'show_on_site' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('display_name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function name(): string
    {
        if (filled($this->display_name)) {
            return $this->display_name;
        }

        return $this->relationLoaded('user') || $this->user()->exists()
            ? $this->user->name
            : 'Barber';
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function onSite(Builder $query): void
    {
        $query->where('show_on_site', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('display_name');
    }
}
