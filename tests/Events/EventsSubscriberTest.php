<?php

use Tests\Events\Fixtures\DeclarativeSubscriber;
use Tests\Events\Fixtures\ExampleSubscriber;
use Voyager\Events\Dispatcher;
use Voyager\Vessel\Vessel;

test('event subscribers', function () {
    $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
    $subs = Mockery::mock(ExampleSubscriber::class);
    $subs->shouldReceive('subscribe')->once()->with($d);
    $vessel->shouldReceive('make')->once()->with(ExampleSubscriber::class)->andReturn($subs);

    $d->subscribe(ExampleSubscriber::class);
});

test('event subscribe can accept object', function () {
    $d = new Dispatcher;
    $subs = Mockery::mock(ExampleSubscriber::class);
    $subs->shouldReceive('subscribe')->once()->with($d);

    $d->subscribe($subs);
});

test('event subscribe can return mappings', function () {
    $d = new Dispatcher;
    $d->subscribe(DeclarativeSubscriber::class);

    $d->dispatch('myEvent1');
    expect(DeclarativeSubscriber::$string)->toBe('L1_L2_');

    $d->dispatch('myEvent2');
    expect(DeclarativeSubscriber::$string)->toBe('L1_L2_L3');
});
