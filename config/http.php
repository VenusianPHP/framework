<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Async drivers
    |--------------------------------------------------------------------------
    |
    | Which loop handler carries an async() request. "curl" uses ext-curl's
    | multi interface and owns the loop's sleep while requests are in
    | flight. "multicurl" needs the multicurl extension and hands curl's sockets to
    | the loop as streams. "auto" picks multicurl when it is loaded, curl
    | otherwise. Sync requests never touch a driver.
    | Available drivers: 'auto' | 'curl' | 'multicurl'
    */

    'async' => [
        'default' => env('HTTP_ASYNC_DRIVER', 'auto'),
        'drivers' => [
            'curl'      => ['driver' => 'curl',      'max_handles' => 50, 'multi_options' => []],
            'multicurl' => ['driver' => 'multicurl', 'max_handles' => 50],
        ],
    ],

];
