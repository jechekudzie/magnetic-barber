<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $client_id
 * @property int|null $appointment_id
 * @property string $type
 * @property int $points
 * @property int $balance_after
 * @property string|null $description
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'client_id', 'branch_id', 'appointment_id', 'type', 'points',
    'balance_after', 'description', 'expires_at', 'created_by',
])]
class LoyaltyLedger extends Model
{
    protected $table = 'loyalty_ledger';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
