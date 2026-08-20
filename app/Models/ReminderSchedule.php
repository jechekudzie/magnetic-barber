<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $client_id
 * @property string $type
 * @property CarbonInterface $due_at
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $cancelled_at
 * @property int|null $days_since_visit
 */
#[Fillable([
    'client_id', 'branch_id', 'appointment_id', 'type', 'due_at',
    'sent_at', 'cancelled_at', 'cancelled_reason', 'days_since_visit',
])]
class ReminderSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Still waiting to be acted on.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('sent_at')->whereNull('cancelled_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function due(Builder $query): void
    {
        $query->pending()->where('due_at', '<=', now());
    }
}
