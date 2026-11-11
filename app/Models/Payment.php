<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One payment against one booking.
 *
 * @property int $appointment_id
 * @property int $branch_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $reference
 * @property string|null $proof_path
 * @property CarbonInterface|null $paid_at
 * @property int|null $recorded_by
 * @property int|null $verified_by
 * @property CarbonInterface|null $verified_at
 * @property string|null $rejection_reason
 * @property string|null $note
 */
#[Fillable([
    'appointment_id', 'branch_id', 'method', 'status', 'amount_cents', 'currency',
    'reference', 'proof_path', 'paid_at', 'recorded_by', 'verified_by',
    'verified_at', 'rejection_reason', 'note',
])]
class Payment extends Model
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Who put the row here. Null when a client recorded it themselves. */
    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function amount(): Money
    {
        return Money::ofCents($this->amount_cents, $this->currency);
    }

    /**
     * Where to look at the proof.
     *
     * A route rather than a storage URL: the file sits on the private disk,
     * because a screenshot of an EcoCash payment carries a phone number, a
     * name and an amount, and an unguessable filename on a public disk is
     * obscurity rather than protection. The route checks who is asking.
     */
    public function proofUrl(): ?string
    {
        return $this->proof_path === null
            ? null
            : route('payments.proof', ['payment' => $this->ulid]);
    }

    /**
     * Still waiting for somebody at the shop to look at it. This is the queue
     * the desk works through, so it is a scope rather than a filter somebody
     * rewrites in each controller.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awaitingCheck(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function counted(Builder $query): void
    {
        $query->where('status', PaymentStatus::Confirmed);
    }
}
