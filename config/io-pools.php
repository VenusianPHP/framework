<?php

return [
    'resources' => [
        'http' => [
            'enabled' => true,
            'default' => 'multi-curl',
            'drivers' => [
                'multi-curl' => [

                ]
            ]
        ],
        'async' => [
            'enabled' => false,
            'default' => 'redis',
            'drivers' => [
                'redis' => [
                    'connection' => 'default',
                    'key' => 'io-pool:mail',
                    'batch' => 64,
                ]
            ]
        ]
    ]
];