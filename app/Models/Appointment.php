<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $reference
 * @property int $branch_id
 * @property int $client_id
 * @property int|null $staff_id
 * @property AppointmentType $type
 * @property AppointmentStatus $status
 * @property string $source
 * @property CarbonInterface|null $scheduled_start_at
 * @property CarbonInterface|null $scheduled_end_at
 * @property int|null $style_id
 * @property string|null $client_note
 * @property int $subtotal_cents
 * @property int $total_cents
 * @property string $currency
 * @property int $duration_minutes
 */
#[Fillable([
    'reference', 'branch_id', 'client_id', 'staff_id', 'type', 'status', 'source',
    'scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'started_at',
    'completed_at', 'cancelled_at', 'queue_position', 'estimated_wait_minutes',
    'style_id', 'client_note', 'staff_note', 'subtotal_cents', 'travel_fee_cents',
    'discount_cents', 'total_cents', 'currency', 'duration_minutes',
    'cancellation_reason', 'cancelled_by', 'created_by', 'slot_key',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        // Kept in one place so no caller can forget it and quietly reopen the
        // double booking hole, or leave a cancelled slot permanently blocked.
        static::saving(function (self $appointment): void {
            $appointment->slot_key = $appointment->slotKey();
        });
    }

    /**
     * The value the unique index guards, or null when this appointment no
     * longer holds its slot.
     *
     * Public because seeders run with model events off, and would otherwise
     * write rows the database guard does not cover.
     */
    public function slotKey(): ?string
    {
        return $this->holdsItsSlot()
            ? "{$this->staff_id}@{$this->scheduled_start_at?->utc()->format('Y-m-d H:i')}"
            : null;
    }

    /**
     * Whether this appointment still occupies its slot. A cancelled or
     * completed one does not, so the time becomes bookable again.
     */
    public function holdsItsSlot(): bool
    {
        return $this->staff_id !== null
            && $this->scheduled_start_at !== null
            && in_array($this->status->value, AppointmentStatus::blocking(), true);
    }

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Crockford base32 without I, L, O or U, so nobody misreads a reference
     * read aloud over the phone.
     */
    public static function generateReference(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        do {
            $code = '';

            for ($i = 0; $i < 5; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = "MB-A{$code}";
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<Style, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    /** @return HasMany<AppointmentService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    /** @return HasOne<HouseCallDetail, $this> */
    public function houseCall(): HasOne
    {
        return $this->hasOne(HouseCallDetail::class);
    }

    public function isHouseCall(): bool
    {
        return $this->type === AppointmentType::HouseCall;
    }

    public function total(): Money
    {
        return Money::ofCents($this->total_cents, $this->currency);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::blocking());
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->blocking()->where('scheduled_start_at', '>=', now());
    }

    /**
     * A friendly, ambiguity-free label for a confirmation screen.
     */
    public function whenLabel(): string
    {
        if ($this->scheduled_start_at === null) {
            return 'Walk in';
        }

        $local = $this->scheduled_start_at->timezone($this->branch->timezone);

        return Str::of($local->format('l j F, g:ia'))->replace(['am', 'pm'], ['am', 'pm'])->toString();
    }
}
