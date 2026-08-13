<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $appointment_id
 * @property int|null $client_id
 * @property int|null $staff_id
 * @property int|null $branch_id
 * @property string|null $author_name
 * @property int $rating
 * @property string|null $comment
 * @property bool $is_public
 * @property Carbon|null $published_at
 */
#[Fillable(['appointment_id', 'client_id', 'staff_id', 'branch_id', 'author_name', 'rating', 'comment', 'is_public', 'published_at', 'responded_by', 'response', 'responded_at', 'flagged_at'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'responded_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_public', true)
            ->whereNotNull('published_at')
            ->whereNull('flagged_at');
    }
}
