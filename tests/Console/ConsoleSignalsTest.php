<?php

use Voyager\Console\ConsoleSignals;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;

/** The handlers the registry holds, read from its private property. */
function registryHandlers(SignalRegistry $registry): array
{
    return (fn () => $this->signalHandlers)->call($registry);
}

it('runs the newest handler first, and restores the registry on unregister', function () {
    $registry = new SignalRegistry;
    $original = fn () => null;
    $registry->register(SIGUSR2, $original);
    $before = registryHandlers($registry);

    $signals = new ConsoleSignals($registry);
    $first = fn () => null;
    $second = fn () => null;
    $signals->register(SIGUSR2, $first);
    $signals->register(SIGUSR2, $second);

    expect(registryHandlers($registry)[SIGUSR2][0])->toBe($second);

    $signals->unregister();

    expect(registryHandlers($registry))->toBe($before);
})->skip(! extension_loaded('pcntl'), 'needs ext-pcntl');
