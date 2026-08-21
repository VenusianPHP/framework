<?php

use Voyager\NutsAndBolts\DataObjects\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Supported drivers for Venusian: "file" and "redis". The "array" store
    | exists for in-process tests and the RateLimiter default — not for
    | production workloads. The database store arrives with Database.
    |
    */

    'default' => env('CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        // 'database' => [                                    // lands in wave 7
        //     'driver' => 'database',
        //     'connection' => env('DB_CACHE_CONNECTION'),
        //     'table' => env('DB_CACHE_TABLE', 'cache'),
        //     'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
        //     'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        // ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'venusian')).'-cache-'),

];
