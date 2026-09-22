<?php

use Voyager\Signals\NullDispatcher;
use Voyager\Signals\SignalDispatcher;

test('null dispatcher forwards listen but swallows dispatch', function () {
    $real = new SignalDispatcher;
    $hits = 0;
    $null = new NullDispatcher($real);

    $null->listen('ping', function () use (&$hits) { $hits++; });
    expect($real->hasListeners('ping'))->toBeTrue();

    expect($null->dispatch('ping'))->toBeNull();
    expect($hits)->toBe(0);

    $real->dispatch('ping');
    expect($hits)->toBe(1);

    $null->forget('ping');
    expect($real->hasListeners('ping'))->toBeFalse();
    expect($null->getDispatcher())->toBe($real);
});
