<?php

// usage: exit-without-waiting.php <log path>
// A script that logs async and exits without running the loop or waiting on the promise.

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Venusian\Tests\Log\Fixtures\LogApp;

$app = LogApp::boot();
$app['config']->set('logging.channels.leaving', ['driver' => 'single', 'path' => $argv[1], 'level' => 'debug']);

$app->get('log')->channel('leaving')->infoAsync('written after the script ended');
