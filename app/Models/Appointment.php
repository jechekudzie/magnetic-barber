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
 * @property int|null $assigned_by
 * @property CarbonInterface|null $assigned_at
 * @property int|null $started_by
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
 * @property CarbonInterface|null $checked_in_at
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 * @property int $amount_paid_cents
 * @property CarbonInterface|null $secured_at
 * @property int|null $actual_minutes
 */
#[Fillable([
    'reference', 'branch_id', 'client_id', 'staff_id', 'type', 'status', 'source',
    'assigned_by', 'assigned_at', 'started_by',
    'scheduled_start_at', 'scheduled_end_at', 'checked_in_at', 'started_at',
    'completed_at', 'cancelled_at', 'queue_position', 'estimated_wait_minutes',
    'amount_paid_cents', 'secured_at', 'actual_minutes',
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
            'assigned_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'secured_at' => 'datetime',
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

    /**
     * Who put this barber in this chair. Null for a booking that still has
     * the barber it was created with.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Who tapped Start: the desk, or the barber working alone.
     *
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
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
    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * A chair is spoken for once money has been claimed against it, even
     * before the desk has checked the proof. Whether the shop has actually
     * been paid is amountPaid(), which is a different question.
     */
    public function isSecured(): bool
    {
        return $this->secured_at !== null;
    }

    public function amountPaid(): Money
    {
        return Money::ofCents($this->amount_paid_cents, $this->currency);
    }

    public function outstanding(): Money
    {
        $due = $this->total_cents - $this->amount_paid_cents;

        return Money::ofCents(max(0, $due), $this->currency);
    }

    public function isPaidInFull(): bool
    {
        return $this->amount_paid_cents >= $this->total_cents;
    }

    /**
     * How long the barber actually took, in minutes.
     *
     * Reads the stored figure once the cut is finished, and the clock while it
     * is still running, so a diary can show a live timer without the caller
     * knowing which case it is in.
     */
    public function minutesSoFar(): ?int
    {
        if ($this->actual_minutes !== null) {
            return $this->actual_minutes;
        }

        if ($this->started_at === null) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return (int) $this->started_at->diffInMinutes($end);
    }

    /**
     * Whether this cut is past the time the catalog allowed for it. The chair
     * is still occupied, whatever the diary says it should have been.
     */
    public function isOverrunning(): bool
    {
        return $this->status === AppointmentStatus::InProgress
            && $this->scheduled_end_at !== null
            && $this->scheduled_end_at->isPast();
    }

    public function whenLabel(): string
    {
        if ($this->scheduled_start_at === null) {
            return 'Walk in';
        }

        $local = $this->scheduled_start_at->timezone($this->branch->timezone);

        return Str::of($local->format('l j F, g:ia'))->replace(['am', 'pm'], ['am', 'pm'])->toString();
    }
}
