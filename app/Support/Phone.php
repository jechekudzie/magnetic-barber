<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * One place that knows how to turn anything a client says into the single
 * stored form. Reception creating a duplicate client is the most common data
 * problem in a shop system, and it always starts with an unnormalised number.
 */
final class Phone
{
    /**
     * Returns E.164 (+263781879820), or the original string when it cannot be
     * parsed so validation reports it rather than the cast throwing.
     */
    public static function normalise(?string $value, ?string $country = null): ?string
    {
        if (blank($value)) {
            return null;
        }

        $country ??= config('magnetic.phone_country', 'ZW');

        try {
            return (new PhoneNumber($value, $country))->formatE164();
        } catch (NumberParseException) {
            return $value;
        }
    }

    /**
     * National format for reading on screen: 078 187 9820.
     */
    public static function forDisplay(?string $value, ?string $country = null): ?string
    {
        if (blank($value)) {
            return null;
        }

        $country ??= config('magnetic.phone_country', 'ZW');

        try {
            return (new PhoneNumber($value, $country))->formatNational();
        } catch (NumberParseException) {
            return $value;
        }
    }

    /**
     * Logs and support screens never need the middle digits: +2637****820.
     */
    public static function mask(?string $value): ?string
    {
        if (blank($value) || strlen($value) < 8) {
            return $value;
        }

        return substr($value, 0, 5).str_repeat('*', 4).substr($value, -3);
    }

    /**
     * Strips everything a wa.me link cannot take.
     */
    public static function forWhatsAppLink(?string $value, ?string $country = null): ?string
    {
        $normalised = self::normalise($value, $country);

        return $normalised === null ? null : ltrim($normalised, '+');
    }
}
