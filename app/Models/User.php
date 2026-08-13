<?php

namespace App\Models;

use App\Casts\PhoneNumber;
use App\Support\Phone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff and clients live in this one table, separated by role. Clients have a
 * phone and no password; staff have a password and an optional till PIN.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $avatar_path
 * @property string $locale
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $points_balance Aggregate, only present when withSum is used
 */
#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens, HasRoles, HasUlids, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'phone' => PhoneNumber::class,
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasOne<StaffProfile, $this> */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /** @return HasOne<ClientProfile, $this> */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    /** @return HasMany<LoyaltyLedger, $this> */
    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(LoyaltyLedger::class, 'client_id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['employment_type', 'commission_rate', 'chair_rate_cents', 'currency', 'is_primary', 'starts_on', 'ends_on'])
            ->withTimestamps();
    }

    public function worksAt(int $branchId): bool
    {
        return $this->branches()->whereKey($branchId)->exists();
    }

    public function isStaff(): bool
    {
        return $this->staffProfile()->exists();
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Always search on the normalised number, never on what was typed.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withPhone(Builder $query, string $phone): void
    {
        $query->where('phone', Phone::normalise($phone));
    }
}
