<?php

use Tests\Console\Fixtures\FakeSignalsRegistry;
use Voyager\Console\Command;
use Voyager\Console\Signals;

/** A command wired to trap signals through the given fake registry. */
function commandTrappingOn(FakeSignalsRegistry $registry): Command
{
    $command = new Command;

    (fn () => $this->signals = new Signals($registry))->call($command);

    return $command;
}

beforeEach(function () {
    Signals::resolveAvailabilityUsing(fn () => true);
});

test('a trap runs its handler when signals are available', function () {
    $registry = new FakeSignalsRegistry;
    $state = null;

    commandTrappingOn($registry)->trap('my-signal', function () use (&$state) {
        $state = 'taylorotwell';
    });

    $registry->handle('my-signal');

    expect($state)->toBe('taylorotwell');
});

test('a trap is inert when signals are unavailable', function () {
    Signals::resolveAvailabilityUsing(fn () => false);

    $registry = new FakeSignalsRegistry;
    $state = null;

    commandTrappingOn($registry)->trap('my-signal', function () use (&$state) {
        $state = 'taylorotwell';
    });

    $registry->handle('my-signal');

    expect($state)->toBeNull();
});

test('untrap removes the handler', function () {
    $registry = new FakeSignalsRegistry;
    $state = null;

    $command = commandTrappingOn($registry);

    $command->trap('my-signal', function () use (&$state) {
        $state = 'taylorotwell';
    });

    $command->untrap();

    $registry->handle('my-signal');

    expect($state)->toBeNull();
});

test('nested traps run last registered first and unwind independently', function () {
    $registry = new FakeSignalsRegistry;
    $state = '';

    $a = commandTrappingOn($registry);
    $a->trap('my-signal', function () use (&$state) {
        $state .= '1';
    });

    $b = commandTrappingOn($registry);
    $b->trap('my-signal', function () use (&$state) {
        $state .= '2';
    });

    $c = commandTrappingOn($registry);
    $c->trap('my-signal', function () use (&$state) {
        $state .= '3';
    });

    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('321');

    $c->untrap();
    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('21');

    $d = commandTrappingOn($registry);
    $d->trap('my-signal', function () use (&$state) {
        $state .= '3';
    });

    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('321');

    $d->untrap();
    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('21');

    $b->untrap();
    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('1');

    $a->untrap();
    $state = '';
    $registry->handle('my-signal');
    expect($state)->toBe('');
});
