<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Writes the code to the log instead of sending it.
 *
 * The default until an SMS or WhatsApp account exists. Because it does not
 * deliver, the auth controller returns the code in the response on local and
 * testing only, so the flow can be driven end to end. That branch is keyed on
 * delivers(), never on the environment alone, so configuring a real channel
 * closes it even if someone leaves APP_ENV wrong.
 */
final class LogOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code, int $expiresInMinutes): void
    {
        Log::info('Login code issued', [
            'phone' => $phone,
            'code' => $code,
            'expires_in_minutes' => $expiresInMinutes,
        ]);
    }

    public function delivers(): bool
    {
        return false;
    }
}
