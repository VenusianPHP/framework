<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Concurrency Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default concurrency driver used by Venusian's
    | concurrency functions. By default, concurrent work is sent to isolated
    | PHP processes, each of which returns its result to the caller.
    |
    | The "fork" driver needs the "spatie/fork" package and may only be used
    | from the console.
    |
    | Supported: "process", "fork", "sync"
    |
    */

    'default' => env('CONCURRENCY_DRIVER', 'process'),

];
