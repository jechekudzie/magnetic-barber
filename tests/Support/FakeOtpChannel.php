<?php

namespace Tests\Support;

use App\Services\Otp\OtpChannel;
use App\Support\Phone;

/**
 * A channel that claims to deliver, and remembers what it sent.
 *
 * The log channel cannot stand in for a real one in these tests any more:
 * the code flow is shut while nothing delivers, so exercising it needs
 * something that says it does. Reading the code off the channel rather than
 * out of the response is also the better test — it proves the code reached
 * the sender, which is the thing a real deployment depends on.
 */
class FakeOtpChannel implements OtpChannel
{
    /** @var array<string, string> Latest code per E.164 number. */
    public array $sent = [];

    public function send(string $phone, string $code, int $expiresInMinutes): void
    {
        $this->sent[$phone] = $code;
    }

    public function delivers(): bool
    {
        return true;
    }

    /** The code most recently sent to a number, however it was typed. */
    public function codeFor(string $phone): ?string
    {
        return $this->sent[Phone::normalise($phone)] ?? null;
    }
}
