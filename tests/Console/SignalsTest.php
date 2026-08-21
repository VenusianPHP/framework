<?php

use Tests\Console\Fixtures\FakeSignalsRegistry;
use Voyager\Console\Signals;

test('handlers registered for one signal all run, last registered first', function () {
    $registry = new FakeSignalsRegistry;
    $signals = new Signals($registry);
    $state = null;

    $signals->register('my-signal', function () use (&$state) {
        $state .= 'otwell';
    });

    $signals->register('my-signal', function () use (&$state) {
        $state = 'taylor';
    });

    $registry->handle('my-signal');

    expect($state)->toBe('taylorotwell');
});

test('unregister removes every handler', function () {
    $registry = new FakeSignalsRegistry;
    $signals = new Signals($registry);
    $state = null;

    $signals->register('my-signal', function () use (&$state) {
        $state .= 'otwell';
    });

    $signals->register('my-signal', function () use (&$state) {
        $state = 'taylor';
    });

    $signals->unregister();

    $registry->handle('my-signal');

    expect($state)->toBeNull();
});
