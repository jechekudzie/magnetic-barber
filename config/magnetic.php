<?php

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

];
