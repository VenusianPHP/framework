<?php

use Voyager\Config\Repository as Config;
use Voyager\IOPools\EventLoop;
use Voyager\Vessel\ControlPanel;

function httpVessel(array $http = [], bool $loop = true): ControlPanel
{
    $vessel = new ControlPanel;
    $vessel->registerInstance('config', new Config(['http' => array_replace_recursive(
        ['async' => ['default' => getenv('HTTP_ASYNC_DRIVER') ?: 'curl', 'drivers' => ['curl' => ['driver' => 'curl'], 'pcurl' => ['driver' => 'pcurl']]]],
        $http,
    )]));
    if ($loop) { $vessel->registerInstance('event-loop', new EventLoop); }
    return $vessel;
}
