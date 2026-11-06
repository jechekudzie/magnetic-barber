<?php

use App\Services\Otp\LogOtpChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | Phone numbers are the identity key and are stored in E.164. This is the
    | country we assume when a client types a local number like 0781879820.
    |
    */

    'phone_country' => env('MAGNETIC_PHONE_COUNTRY', 'ZW'),

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | Transactions happen in whatever was tendered. Reports default to USD.
    |
    */

    'default_currency' => env('MAGNETIC_DEFAULT_CURRENCY', 'USD'),
    'reporting_currency' => env('MAGNETIC_REPORTING_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Pay
    |--------------------------------------------------------------------------
    |
    | What a barber earns on every service line they personally render, as a
    | percentage of that line. This is the house rate; branch_user.commission_rate
    | overrides it for an individual barber.
    |
    */

    'commission_rate' => (float) env('MAGNETIC_COMMISSION_RATE', 5),

    /*
    |--------------------------------------------------------------------------
    | Client sign in
    |--------------------------------------------------------------------------
    |
    | Clients have no password: the phone number is the identity and a one time
    | code proves they hold it. Staff sign in with a password instead.
    |
    | otp_channel is the class that delivers the code. The default writes it to
    | the log, which is what makes the flow testable before the shop has an SMS
    | or WhatsApp account; point it at a real channel to start delivering.
    |
    */

    'otp_minutes' => (int) env('MAGNETIC_OTP_MINUTES', 10),

    /*
     * How close to their appointment a client may still call it off from the
     * app. Inside this window they have to phone the shop, because a chair
     * nobody can refill is a chair the shop loses.
     */
    'cancellation_hours' => (int) env('MAGNETIC_CANCELLATION_HOURS', 2),
    'otp_channel' => env('MAGNETIC_OTP_CHANNEL', LogOtpChannel::class),

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | Used by the public site and by WhatsApp deep links.
    |
    */

    'whatsapp' => env('MAGNETIC_WHATSAPP', '+263781879820'),
    'instagram' => env('MAGNETIC_INSTAGRAM', 'magnetic_barbershop'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | The public catalog changes rarely and is read on every page load.
    |
    */

    'catalog_cache_seconds' => (int) env('MAGNETIC_CATALOG_CACHE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long a client may go without a cut before the shop chases them. Used
    | only for clients whose own visit rhythm is not yet known.
    |
    */

    'winback_days' => (int) env('MAGNETIC_WINBACK_DAYS', 21),

    // Where the shop actually is, for anything scheduled by the clock.
    'timezone' => env('MAGNETIC_TIMEZONE', 'Africa/Harare'),

];
