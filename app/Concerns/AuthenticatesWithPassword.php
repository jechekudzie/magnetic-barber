<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in with an identifier and a password.
 *
 * Shared by the client and the staff doors because both accept an email or a
 * phone number: not every barber has a work email, and not every client gives
 * one. Duplicating it would mean two places to get the timing and the
 * throttling right, and one of them would drift.
 */
trait AuthenticatesWithPassword
{
    /** Wrong guesses tolerated per identifier and IP before the door shuts. */
    private int $maxAttempts = 5;

    private int $lockoutSeconds = 60;

    /**
     * A valid bcrypt hash of nothing anybody knows, so an unknown identifier
     * still costs a full hash comparison.
     */
    private string $decoyHash = '$2y$12$ZZZZZZZZZZZZZZZZZZZZZeZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ';

    /**
     * Find the account behind an email or a phone number, check the password,
     * and throttle whoever is guessing.
     *
     * Returns null for every failure, and the caller reports all of them the
     * same way: which one it was tells an attacker which identifiers are worth
     * their time.
     */
    protected function attemptPasswordLogin(
        Request $request,
        string $identifier,
        string $password,
        string $scope,
    ): ?User {
        $key = $this->throttleKey($scope, $identifier, $request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many attempts. Try again in '
                    .RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = $this->findByIdentifier($identifier);

        /*
         * Compare against a real hash whether or not the account exists, so
         * the response takes the same time either way. Returning faster for an
         * unknown identifier is itself an answer.
         */
        $knownHash = $user?->password;
        $matches = Hash::check($password, $knownHash ?? $this->decoyHash);

        if ($user === null || $knownHash === null || ! $matches) {
            RateLimiter::hit($key, $this->lockoutSeconds);

            return null;
        }

        RateLimiter::clear($key);

        return $user;
    }

    /**
     * An email or a phone number. The number is normalised first, so
     * 0781879820 and +263781879820 find the same person.
     */
    protected function findByIdentifier(string $identifier): ?User
    {
        if (Str::contains($identifier, '@')) {
            return User::query()->where('email', $identifier)->first();
        }

        $phone = Phone::normalise($identifier);

        return $phone === null
            ? null
            : User::query()->where('phone', $phone)->first();
    }

    /**
     * One message for every way a sign in can fail.
     */
    protected function refuseLogin(): never
    {
        throw ValidationException::withMessages([
            'identifier' => 'Those details do not match our records.',
        ]);
    }

    private function throttleKey(string $scope, string $identifier, Request $request): string
    {
        return $scope.'-login:'
            .Str::transliterate(Str::lower($identifier))
            .'|'.$request->ip();
    }
}
