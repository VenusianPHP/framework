<?php

use Carbon\CarbonImmutable;
use Voyager\MagicAliases\Date;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\System\Testing\Wormhole;

afterEach(function () {
    Date::useDefault();
});

test('it can travel back to the present', function () {
    // Preserve the timelines we want to compare the reality with...
    $present = now();
    $future = now()->addDays(10);

    // Travel in time...
    (new Wormhole(10))->days();

    // Assert we are now in the future...
    expect(now()->format('Y-m-d'))->toEqual($future->format('Y-m-d'));

    // Assert we can go back to the present...
    expect(Wormhole::back()->format('Y-m-d'))->toEqual($present->format('Y-m-d'));
});

test('it is compatible with CarbonImmutable', function () {
    // Tell the Date Factory to use CarbonImmutable...
    Date::use(CarbonImmutable::class);

    // Record what time it is in 10 days...
    $present = now();
    $future = $present->addDays(10);

    // Travel in time...
    (new Wormhole(10))->days();

    // Assert that the present time didn't get mutated...
    expect($present->format('Y-m-d'))->not->toEqual($future->format('Y-m-d'));

    // Assert the time travel was successful...
    expect(now()->format('Y-m-d'))->toEqual($future->format('Y-m-d'));
});

test('it can travel by microseconds', function () {
    Carbon::setTestNow(Carbon::parse('2000-01-01 00:00:00')->startOfSecond());

    (new Wormhole(1))->microsecond();
    expect(Date::now()->format('Y-m-d H:i:s.u'))->toBe('2000-01-01 00:00:00.000001');

    (new Wormhole(5))->microseconds();
    expect(Date::now()->format('Y-m-d H:i:s.u'))->toBe('2000-01-01 00:00:00.000006');

    Carbon::setTestnow();
});
