<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property int $points_per_visit
 * @property float $points_per_currency_unit
 * @property int $redemption_threshold
 * @property int $redemption_value_cents
 * @property int|null $points_expiry_months
 * @property string $currency
 * @property bool $is_active
 */
#[Fillable([
    'name', 'points_per_visit', 'points_per_currency_unit', 'applies_to',
    'min_spend_cents', 'redemption_threshold', 'redemption_value_cents',
    'points_expiry_months', 'currency', 'is_active',
])]
class LoyaltyRule extends Model
{
    protected function casts(): array
    {
        return [
            'points_per_currency_unit' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The rule in force. There is deliberately only ever one active rule: two
     * overlapping earn rates is the fastest way to a balance nobody can explain.
     */
    public static function current(): self
    {
        return self::query()->where('is_active', true)->latest('id')->first()
            ?? new self([
                'name' => 'Default',
                'points_per_visit' => 5,
                'points_per_currency_unit' => 0,
                'redemption_threshold' => 50,
                'redemption_value_cents' => 500,
                'currency' => config('magnetic.default_currency'),
                'is_active' => true,
            ]);
    }

    public function redemptionValue(): Money
    {
        return Money::ofCents($this->redemption_value_cents, $this->currency);
    }

    /**
     * What one point is worth, used to show "45 points is about $4.50".
     */
    public function valueOfCents(int $points): int
    {
        if ($this->redemption_threshold < 1) {
            return 0;
        }

        $blocks = intdiv($points, $this->redemption_threshold);

        return $blocks * $this->redemption_value_cents;
    }
}
