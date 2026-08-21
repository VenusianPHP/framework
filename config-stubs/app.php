<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'Venusian'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */

    'env' => env('APP_ENV', 'production'),

    'timezone' => 'UTC',

    'locale' => 'en',

    'fallback_locale' => 'en',

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        Voyager\Log\LogServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Magic Aliases
    |--------------------------------------------------------------------------
    |
    | Venusian's equivalent of Laravel's class aliases, resolved through
    | Voyager\MagicAliases.
    |
    */

    'aliases' => [
        'Config' => Voyager\NutsAndBolts\MagicAliases\Config::class,
        'Date'   => Voyager\NutsAndBolts\MagicAliases\Date::class,
        'Log'    => Voyager\NutsAndBolts\MagicAliases\Log::class,
    ],

];
