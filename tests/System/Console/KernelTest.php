<?php

use Symfony\Component\Console\Input\StringInput;
use Voyager\Events\Dispatcher;
use Voyager\System\Application;
use Voyager\System\Console\Kernel;
use Voyager\System\Events\Terminating;

test('terminating dispatches the event before the terminating callbacks', function () {
    $called = [];
    $app = new Application;
    $events = new Dispatcher($app);
    $app->instance('events', $events);
    $kernel = new Kernel($app, $events);
    $events->listen(function (Terminating $terminating) use (&$called) {
        $called[] = 'terminating event';
    });
    $app->terminating(function () use (&$called) {
        $called[] = 'terminating callback';
    });

    $kernel->terminate(new StringInput('tinker'), 0);

    expect($called)->toBe([
        'terminating event',
        'terminating callback',
    ]);
});
