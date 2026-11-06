<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A one time code sent to a phone number.
 *
 * @property string $phone
 * @property string $code_hash
 * @property string $purpose
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 * @property int $attempts
 * @property string|null $request_ip
 */
#[Fillable(['phone', 'code_hash', 'purpose', 'expires_at', 'consumed_at', 'attempts', 'request_ip'])]
class VerificationCode extends Model
{
    public const PURPOSE_LOGIN = 'login';

    /**
     * How many wrong guesses a single code tolerates before it is burned.
     */
    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Codes that could still be used: not spent, not expired, not guessed to
     * death.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < self::MAX_ATTEMPTS;
    }
}
