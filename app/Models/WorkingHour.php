<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $branch_id
 * @property int $user_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 */
#[Fillable(['branch_id', 'user_id', 'weekday', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
