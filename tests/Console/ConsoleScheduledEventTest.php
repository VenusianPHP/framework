<?php

use Voyager\Console\Scheduling\Event;
use Voyager\Console\Scheduling\EventMutex;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\System\Application;

/** An application that is up and running in production. */
function scheduledEventApplication(): Mockery\MockInterface
{
    $app = Mockery::mock(Application::class.'[isDownForMaintenance,environment]');
    $app->shouldReceive('isDownForMaintenance')->andReturn(false);
    $app->shouldReceive('environment')->andReturn('production');

    return $app;
}

/** A bare `php foo` event, optionally pinned to a timezone. */
function scheduledEvent(?string $timezone = null): Event
{
    return $timezone === null
        ? new Event(Mockery::mock(EventMutex::class), 'php foo')
        : new Event(Mockery::mock(EventMutex::class), 'php foo', $timezone);
}

beforeEach(function () {
    $this->defaultTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');
});

afterEach(function () {
    date_default_timezone_set($this->defaultTimezone);
    Carbon::setTestNow(null);
});

describe('cron compilation', function () {
    test('an event runs every minute unless it is skipped', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->getExpression())->toBe('* * * * *')
            ->and($event->isDue($app))->toBeTrue()
            ->and($event->skip(fn () => true)->isDue($app))->toBeTrue()
            ->and($event->skip(fn () => true)->filtersPass($app))->toBeFalse();
    });

    test('an event restricted to another environment is not due', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->getExpression())->toBe('* * * * *')
            ->and($event->environments('local')->isDue($app))->toBeFalse();
    });

    test('a when callback returning false fails the filters', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->getExpression())->toBe('* * * * *')
            ->and($event->when(fn () => false)->filtersPass($app))->toBeFalse();
    });

    test('a literal false when fails the filters', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->getExpression())->toBe('* * * * *')
            ->and($event->when(false)->filtersPass($app))->toBeFalse();
    });

    test('chained frequency rules are commutative', function ($first, $second) {
        expect(scheduledEvent()->{$first}()->{$second}()->getExpression())
            ->toEqual(scheduledEvent()->{$second}()->{$first}()->getExpression());
    })->with([
        'daily and hourly' => ['daily', 'hourly'],
        'weekdays and hourly' => ['weekdays', 'hourly'],
    ]);
});

describe('isDue', function () {
    beforeEach(function () {
        Carbon::setTestNow(Carbon::create(2015, 1, 1, 0, 0, 0));
    });

    test('a thursday event is due on a thursday', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->thursdays()->getExpression())->toBe('* * * * 4')
            ->and($event->isDue($app))->toBeTrue();
    });

    test('a wednesday evening event in EST is due', function () {
        $app = scheduledEventApplication();

        $event = scheduledEvent();

        expect($event->wednesdays()->at('19:00')->timezone('EST')->getExpression())->toBe('0 19 * * 3')
            ->and($event->isDue($app))->toBeTrue();
    });
});

describe('at 09:00 UTC', function () {
    beforeEach(function () {
        Carbon::setTestNow(Carbon::now()->startOfDay()->addHours(9));
    });

    test('between passes only inside the window', function ($start, $end, $passes) {
        $app = scheduledEventApplication();

        expect(scheduledEvent('UTC')->between($start, $end)->filtersPass($app))->toBe($passes);
    })->with([
        'surrounding window' => ['8:00', '10:00', true],
        'zero width window on the hour' => ['9:00', '9:00', true],
        'window wrapping midnight' => ['23:00', '10:00', true],
        'window wrapping into the morning' => ['8:00', '6:00', true],
        'later window' => ['10:00', '11:00', false],
        'window wrapping past the hour' => ['10:00', '8:00', false],
    ]);

    test('unlessBetween is the inverse of between', function ($start, $end, $passes) {
        $app = scheduledEventApplication();

        expect(scheduledEvent('UTC')->unlessBetween($start, $end)->filtersPass($app))->toBe($passes);
    })->with([
        'surrounding window' => ['8:00', '10:00', false],
        'zero width window on the hour' => ['9:00', '9:00', false],
        'window wrapping midnight' => ['23:00', '10:00', false],
        'window wrapping into the morning' => ['8:00', '6:00', false],
        'later window' => ['10:00', '11:00', true],
        'window wrapping past the hour' => ['10:00', '8:00', true],
    ]);
});
