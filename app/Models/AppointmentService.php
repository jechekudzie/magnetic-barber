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
 */
#[Fillable([
    'appointment_id', 'service_id', 'staff_id', 'name_snapshot',
    'price_cents', 'currency', 'duration_minutes', 'qty',
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

    public function price(): Money
    {
        return Money::ofCents($this->price_cents * $this->qty, $this->currency);
    }
}
