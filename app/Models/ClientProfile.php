<?php

namespace App\Models;

use App\Enums\ClientSource;
use App\Support\Money;
use Database\Factories\ClientProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $account_number
 * @property int|null $home_branch_id
 * @property int|null $preferred_staff_id
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $notes
 * @property ClientSource $source
 * @property int|null $referred_by_user_id
 * @property string $referral_code
 * @property bool $whatsapp_opt_in
 * @property bool $marketing_opt_in
 * @property int $visit_count
 * @property int $lifetime_value_cents
 * @property string $currency
 * @property Carbon|null $first_visit_at
 * @property Carbon|null $last_visit_at
 * @property Carbon|null $marketing_opt_in_at
 */
#[Fillable(['user_id', 'account_number', 'home_branch_id', 'preferred_staff_id', 'date_of_birth', 'gender', 'notes', 'source', 'referred_by_user_id', 'referral_code', 'whatsapp_opt_in', 'sms_opt_in', 'push_opt_in', 'marketing_opt_in', 'marketing_opt_in_at',
    // Visit counters, written when an appointment is completed.
    'first_visit_at', 'last_visit_at', 'visit_count', 'lifetime_value_cents', 'average_cycle_days'])]
class ClientProfile extends Model
{
    /** @use HasFactory<ClientProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => ClientSource::class,
            'date_of_birth' => 'date',
            // Free text about a person is personal data, so it never sits in
            // the clear where a database export can read it.
            'notes' => 'encrypted',
            'whatsapp_opt_in' => 'boolean',
            'sms_opt_in' => 'boolean',
            'push_opt_in' => 'boolean',
            'marketing_opt_in' => 'boolean',
            'marketing_opt_in_at' => 'datetime',
            'first_visit_at' => 'datetime',
            'last_visit_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_staff_id');
    }

    public function lifetimeValue(): Money
    {
        return Money::ofCents($this->lifetime_value_cents, $this->currency);
    }
}
