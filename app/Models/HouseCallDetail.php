<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $appointment_id
 * @property string $address_line
 * @property string|null $area
 * @property string|null $city
 * @property string|null $directions_note
 * @property int $travel_fee_cents
 * @property string $currency
 */
#[Fillable([
    'appointment_id', 'address_line', 'area', 'city', 'directions_note',
    'latitude', 'longitude', 'travel_fee_cents', 'currency', 'distance_km',
    'departed_at', 'arrived_at',
])]
class HouseCallDetail extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function travelFee(): Money
    {
        return Money::ofCents($this->travel_fee_cents, $this->currency);
    }

    public function fullAddress(): string
    {
        return collect([$this->address_line, $this->area, $this->city])
            ->filter()
            ->implode(', ');
    }
}
