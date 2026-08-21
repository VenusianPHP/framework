<?php

use Voyager\NutsAndBolts\Timebox;

/**
 * A Timebox spy whose protected usleep() can be observed.
 */
function timeboxSpy(): Mockery\MockInterface
{
    return Mockery::spy(Timebox::class)->shouldAllowMockingProtectedMethods()->makePartial();
}

test('call executes the callback', function () {
    $called = false;

    (new Timebox)->call(function () use (&$called) {
        $called = true;
    }, 0);

    expect($called)->toBeTrue();
});

test('call waits out the remaining microseconds', function () {
    $mock = timeboxSpy();
    $mock->shouldReceive('usleep')->once();

    $mock->call(function () {
    }, 10000);

    $mock->shouldHaveReceived('usleep')->once();
});

test('call does not sleep once returnEarly has been flagged', function () {
    $mock = timeboxSpy();

    $mock->call(function ($timebox) {
        $timebox->returnEarly();
    }, 10000);

    $mock->shouldNotHaveReceived('usleep');
});

test('call sleeps again once dontReturnEarly has been flagged', function () {
    $mock = timeboxSpy();
    $mock->shouldReceive('usleep')->once();

    $mock->call(function ($timebox) {
        $timebox->returnEarly();
        $timebox->dontReturnEarly();
    }, 10000);

    $mock->shouldHaveReceived('usleep')->once();
});

test('call waits out the microseconds even when the callback throws', function () {
    $mock = timeboxSpy();
    $mock->shouldReceive('usleep')->once();

    try {
        expect(fn () => $mock->call(function () {
            throw new Exception('Exception within Timebox callback.');
        }, 10000))->toThrow(Exception::class, 'Exception within Timebox callback.');
    } finally {
        $mock->shouldHaveReceived('usleep')->once();
    }
});

test('call does not sleep when the callback throws after flagging returnEarly', function () {
    $mock = timeboxSpy();

    try {
        expect(fn () => $mock->call(function ($timebox) {
            $timebox->returnEarly();
            throw new Exception('Exception within Timebox callback.');
        }, 10000))->toThrow(Exception::class, 'Exception within Timebox callback.');
    } finally {
        $mock->shouldNotHaveReceived('usleep');
    }
});
