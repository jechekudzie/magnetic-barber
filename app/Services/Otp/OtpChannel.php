<?php

namespace App\Services\Otp;

/**
 * How a login code actually reaches a client's phone.
 *
 * The shop has no SMS account wired up yet, and reminders are still worked by
 * hand over WhatsApp. Rather than block the app on that procurement, the
 * sending is a driver: the login flow is finished and testable today against
 * the log channel, and switching to a real SMS or WhatsApp gateway later is
 * one class and one config line, with no change to the flow around it.
 */
interface OtpChannel
{
    /**
     * Deliver a code. Throws on a provider failure so the caller can tell the
     * client the code did not go out, rather than leaving them waiting.
     *
     * @param  string  $phone  E.164
     */
    public function send(string $phone, string $code, int $expiresInMinutes): void;

    /**
     * Whether this channel actually delivers to a handset. False for the log
     * driver, which lets local and test environments hand the code back in the
     * response instead of leaving nobody able to sign in.
     */
    public function delivers(): bool;
}
