<?php

namespace App\Services\Otp;

use App\Models\VerificationCode;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and checks the one time codes that sign a client in.
 *
 * Codes are hashed on the way in and only ever compared, never read back. A
 * code is single use, short lived, and burned after a handful of wrong
 * guesses, because a six digit secret is only secret while guessing is
 * expensive.
 */
final class OtpService
{
    public function __construct(private readonly OtpChannel $channel) {}

    /**
     * Issue a code for a number and send it.
     *
     * Any earlier live code for that number is consumed first, so a client who
     * taps "resend" cannot leave two valid codes in play.
     *
     * @return array{expires_in_minutes: int, code: string|null}
     */
    public function issue(string $phone, ?string $ip = null, string $purpose = VerificationCode::PURPOSE_LOGIN): array
    {
        $phone = Phone::normalise($phone);
        $minutes = (int) config('magnetic.otp_minutes');
        $code = $this->generateCode();

        DB::transaction(function () use ($phone, $purpose, $code, $minutes, $ip): void {
            VerificationCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            VerificationCode::create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes($minutes),
                'request_ip' => $ip,
            ]);
        });

        $this->channel->send($phone, $code, $minutes);

        return [
            'expires_in_minutes' => $minutes,
            // Only when nothing actually delivered it, or nobody could log in
            // on a laptop. A real channel closes this off.
            'code' => $this->channel->delivers() ? null : $code,
        ];
    }

    /**
     * Check a code and spend it.
     *
     * Returns false for every failure mode — no code, wrong code, expired,
     * already used, too many guesses — because telling a caller which of those
     * it was tells an attacker which numbers are worth their time.
     */
    public function verify(string $phone, string $code, string $purpose = VerificationCode::PURPOSE_LOGIN): bool
    {
        $phone = Phone::normalise($phone);

        return DB::transaction(function () use ($phone, $code, $purpose): bool {
            /*
             * Locked, because two requests racing with the same code must not
             * both succeed, and because the attempt counter below is a read
             * followed by a write.
             */
            $record = VerificationCode::query()
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($record === null || ! $record->isUsable()) {
                return false;
            }

            if (! Hash::check($code, $record->code_hash)) {
                $record->increment('attempts');

                return false;
            }

            $record->update(['consumed_at' => now()]);

            return true;
        });
    }

    /**
     * Six digits, zero padded, from a cryptographic source.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
