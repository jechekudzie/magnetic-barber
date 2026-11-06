<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $appointment_id
 * @property int|null $service_id
 * @property string $name_snapshot
 * @property int $price_cents
 * @property string $currency
 * @property int $duration_minutes
 * @property int $qty
 * @property float|null $commission_rate
 * @property int $commission_cents
 */
#[Fillable([
    'appointment_id', 'service_id', 'staff_id', 'name_snapshot',
    'price_cents', 'currency', 'duration_minutes', 'qty',
    'commission_rate', 'commission_cents',
])]
class AppointmentService extends Model
{
    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Who actually rendered this line. Not necessarily the barber on the
     * appointment: two barbers can split one booking between them, and the
     * commission follows whoever did the work.
     *
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function price(): Money
    {
        return Money::ofCents($this->price_cents * $this->qty, $this->currency);
    }

    /**
     * What the barber earned on this line, frozen at the rate that was in
     * force when it was attributed.
     */
    public function commission(): Money
    {
        return Money::ofCents($this->commission_cents, $this->currency);
    }
}
