<?php

use Carbon\CarbonInterval;
use PHPUnit\Framework\AssertionFailedError;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\MagicAliases\Date;
use Voyager\NutsAndBolts\Sleep;

afterEach(function () {
    Sleep::fake(false);

    Carbon::setTestNow();
});

/** The microsecond total of a Sleep's accumulated duration. */
function sleptMicroseconds(Sleep $sleep): float
{
    return (float) $sleep->duration->totalMicroseconds;
}

describe('real sleeping', function () {
    beforeEach(fn () => Sleep::fake(false));

    test('it sleeps for whole seconds', function () {
        $start = microtime(true);
        Sleep::for(1)->seconds();
        $end = microtime(true);

        expect($end - $start)->toEqualWithDelta(1, 0.03);
    });

    test('it sleeps for fractional seconds', function () {
        $start = microtime(true);
        Sleep::for(1.5)->seconds();
        $end = microtime(true);

        expect(round($end - $start, 1, PHP_ROUND_HALF_DOWN))->toEqualWithDelta(1.5, 0.03);
    });

    test('faking makes sleeping instant', function () {
        Sleep::fake();

        $start = microtime(true);
        Sleep::for(1.5)->seconds();
        $end = microtime(true);

        expect($end - $start)->toEqualWithDelta(0, 0.03);
    });
});

describe('duration units', function () {
    beforeEach(fn () => Sleep::fake());

    test('a unit method converts to microseconds', function (string $method, int|float $value, float $expected) {
        expect(sleptMicroseconds(Sleep::for($value)->{$method}()))->toBe($expected);
    })->with([
        'minutes'      => ['minutes', 1.5, 90_000_000.0],
        'minute'       => ['minute', 1, 60_000_000.0],
        'seconds'      => ['seconds', 1.5, 1_500_000.0],
        'second'       => ['second', 1, 1_000_000.0],
        'milliseconds' => ['milliseconds', 1.5, 1_500.0],
        'millisecond'  => ['millisecond', 1, 1_000.0],
        // rounded as microseconds is the smallest unit supported...
        'microseconds' => ['microseconds', 1.5, 1.0],
        'microsecond'  => ['microsecond', 1, 1.0],
    ]);

    test('durations may be chained with and', function () {
        $sleep = Sleep::for(1)->second()
            ->and(500)->microseconds();

        expect(sleptMicroseconds($sleep))->toBe(1000500.0);
    });

    test('a DateInterval may be given', function () {
        $sleep = Sleep::for(CarbonInterval::seconds(1)->addMilliseconds(5));

        expect(sleptMicroseconds($sleep))->toBe(1_005_000.0);
    });

    test('a negative duration is silently clamped to zero', function () {
        Sleep::for(-1)->seconds();

        Sleep::assertSequence([
            Sleep::for(0)->seconds(),
        ]);
    });
});

test('a duration with no unit throws', function () {
    Sleep::for(5);
})->throws(RuntimeException::class, 'Unknown duration unit.');

test('then runs the callback and returns its value', function () {
    expect(Sleep::for(1)->milliseconds()->then(fn () => 123))->toEqual(123);
});

test('while repeats the sleep until the callback is falsy', function () {
    $_SERVER['__sleep.while'] = 0;

    $result = Sleep::for(10)->milliseconds()->while(function () {
        static $results = [true, true, false];
        $_SERVER['__sleep.while']++;

        return array_shift($results);
    })->then(fn () => 100);

    expect($_SERVER['__sleep.while'])->toEqual(3)
        ->and($result)->toEqual(100);

    unset($_SERVER['__sleep.while']);
});

describe('sleep and usleep', function () {
    beforeEach(fn () => Sleep::fake());

    test('sleep takes seconds', function () {
        Sleep::sleep(3);

        Sleep::assertSequence([
            Sleep::for(3)->seconds(),
        ]);
    });

    test('usleep takes microseconds', function () {
        Sleep::usleep(3);

        Sleep::assertSequence([
            Sleep::for(3)->microseconds(),
        ]);
    });
});

describe('until', function () {
    beforeEach(fn () => Sleep::fake());

    test('a DateTime sleeps the difference', function () {
        Carbon::setTestNow(now()->startOfDay());

        Sleep::until(now()->addMinute());

        Sleep::assertSequence([
            Sleep::for(60)->seconds(),
        ]);
    });

    test('a timestamp sleeps the difference', function () {
        Carbon::setTestNow(now()->startOfDay());

        Sleep::until(now()->addMinute()->timestamp);

        Sleep::assertSequence([
            Sleep::for(60)->seconds(),
        ]);
    });

    test('a timestamp string sleeps the difference', function () {
        Carbon::setTestNow(now()->startOfDay());

        Sleep::until((string) now()->addMinute()->timestamp);

        Sleep::assertSequence([
            Sleep::for(60)->seconds(),
        ]);
    });

    test('a timestamp string with milliseconds sleeps the difference', function () {
        Carbon::setTestNow('2000-01-01 00:00:00.000'); // 946684800

        Sleep::until('946684899.123');

        Sleep::assertSequence([
            Sleep::for(1)->minute()
                ->and(39)->seconds()
                ->and(123)->milliseconds(),
        ]);
    });

    test('a time in the past sleeps for zero', function () {
        Carbon::setTestNow(now()->startOfDay());

        Sleep::until(now()->subMinutes(100));

        Sleep::assertSequence([
            Sleep::for(0)->seconds(),
        ]);
    });
});

describe('assertions', function () {
    beforeEach(fn () => Sleep::fake());

    test('assertSequence passes for a matching sequence', function () {
        Sleep::for(5)->seconds();
        Sleep::for(1)->seconds()->and(5)->microsecond();

        Sleep::assertSequence([
            Sleep::for(5)->seconds(),
            Sleep::for(1)->seconds()->and(5)->microsecond(),
        ]);
    });

    test('assertSequence reports the mismatched duration', function () {
        Sleep::for(5)->seconds();
        Sleep::for(1)->seconds()->and(5)->microseconds();

        expect(fn () => Sleep::assertSequence([
            Sleep::for(5)->seconds(),
            Sleep::for(9)->seconds()->and(8)->milliseconds(),
        ]))->toThrow(
            AssertionFailedError::class,
            "Expected sleep duration of [9 seconds 8 milliseconds] but actually slept for [1 second 5 microseconds].\nFailed asserting that false is true.",
        );
    });

    test('a zero-second sleep is still recorded', function () {
        Sleep::for(0)->seconds();

        expect(fn () => Sleep::assertSequence([
            Sleep::for(1)->seconds(),
        ]))->toThrow(
            AssertionFailedError::class,
            "Expected sleep duration of [1 second] but actually slept for [0 microseconds].\nFailed asserting that false is true.",
        );
    });

    test('assertSequence reports too many expected sleeps', function () {
        Sleep::for(1)->seconds();

        expect(fn () => Sleep::assertSequence([
            Sleep::for(1)->seconds(),
            Sleep::for(1)->seconds(),
        ]))->toThrow(
            AssertionFailedError::class,
            "Expected [2] sleeps but found [1].\nFailed asserting that 1 is identical to 2.",
        );
    });

    test('instances built for assertions are not themselves recorded', function () {
        Sleep::for(1)->second();

        Sleep::assertSequence([
            Sleep::for(1)->second(),
        ]);

        expect(fn () => Sleep::assertSequence([
            Sleep::for(1)->second(),
            Sleep::for(1)->second(),
        ]))->toThrow(
            AssertionFailedError::class,
            "Expected [2] sleeps but found [1].\nFailed asserting that 1 is identical to 2.",
        );
    });

    test('assertNeverSlept passes before any sleep and fails after', function () {
        Sleep::assertNeverSlept();

        Sleep::for(1)->seconds();

        expect(fn () => Sleep::assertNeverSlept())->toThrow(
            AssertionFailedError::class,
            "Expected [0] sleeps but found [1].\nFailed asserting that 1 is identical to 0.",
        );
    });

    test('assertNeverSlept counts a zero-second sleep', function () {
        Sleep::assertNeverSlept();

        Sleep::for(0)->seconds();

        expect(fn () => Sleep::assertNeverSlept())->toThrow(
            AssertionFailedError::class,
            "Expected [0] sleeps but found [1].\nFailed asserting that 1 is identical to 0.",
        );
    });

    test('assertInsomniac tolerates a zero-second sleep but not a real one', function () {
        Sleep::assertInsomniac();

        Sleep::for(0)->second();

        // we still have not slept...
        Sleep::assertInsomniac();

        Sleep::for(1)->second();

        expect(fn () => Sleep::assertInsomniac())->toThrow(
            AssertionFailedError::class,
            "Unexpected sleep duration of [1 second] found.\nFailed asserting that 1000000 is identical to 0.",
        );
    });

    test('assertSleptTimes counts the sleeps', function () {
        Sleep::assertSleptTimes(0);

        Sleep::for(1)->second();

        Sleep::assertSleptTimes(1);

        expect(fn () => Sleep::assertSleptTimes(0))->toThrow(
            AssertionFailedError::class,
            "Expected [0] sleeps but found [1].\nFailed asserting that 1 is identical to 0.",
        );

        expect(fn () => Sleep::assertSleptTimes(2))->toThrow(
            AssertionFailedError::class,
            "Expected [2] sleeps but found [1].\nFailed asserting that 1 is identical to 2.",
        );
    });

    test('assertSlept matches a duration a given number of times', function () {
        Sleep::assertSlept(fn () => true, 0);

        expect(fn () => Sleep::assertSlept(fn () => true))->toThrow(
            AssertionFailedError::class,
            "The expected sleep was found [0] times instead of [1].\nFailed asserting that 0 is identical to 1.",
        );

        Sleep::for(5)->seconds();

        Sleep::assertSlept(fn (CarbonInterval $duration) => (float) $duration->totalSeconds === 5.0);

        expect(fn () => Sleep::assertSlept(fn (CarbonInterval $duration) => (float) $duration->totalSeconds === 5.0, 2))->toThrow(
            AssertionFailedError::class,
            "The expected sleep was found [1] times instead of [2].\nFailed asserting that 1 is identical to 2.",
        );

        expect(fn () => Sleep::assertSlept(fn (CarbonInterval $duration) => (float) $duration->totalSeconds === 6.0))->toThrow(
            AssertionFailedError::class,
            "The expected sleep was found [0] times instead of [1].\nFailed asserting that 0 is identical to 1.",
        );
    });
});

describe('macros', function () {
    beforeEach(fn () => Sleep::fake());

    test('macros may set, replace and supplement the duration', function () {
        Sleep::macro('forSomeConfiguredAmountOfTime', static function () {
            return Sleep::for(3)->seconds();
        });

        Sleep::macro('useSomeOtherAmountOfTime', function () {
            /** @var Sleep $this */
            return $this->duration(1.234)->seconds();
        });

        Sleep::macro('andSomeMoreGranularControl', function () {
            /** @var Sleep $this */
            return $this->and(567)->microseconds();
        });

        // A static macro can be referenced
        $sleep = Sleep::forSomeConfiguredAmountOfTime();
        expect(sleptMicroseconds($sleep))->toBe(3000000.0);

        // A macro can specify a new duration
        $sleep = $sleep->useSomeOtherAmountOfTime();
        expect(sleptMicroseconds($sleep))->toBe(1234000.0);

        // A macro can supplement an existing duration
        $sleep = $sleep->andSomeMoreGranularControl();
        expect(sleptMicroseconds($sleep))->toBe(1234567.0);
    });

    test('a previously defined duration may be replaced', function () {
        Sleep::macro('setDuration', function ($duration) {
            return $this->duration($duration);
        });

        $sleep = Sleep::for(1)->second();
        expect(sleptMicroseconds($sleep))->toBe(1000000.0);

        $sleep->setDuration(2)->second();
        expect(sleptMicroseconds($sleep))->toBe(2000000.0);

        $sleep->setDuration(500)->milliseconds();
        expect(sleptMicroseconds($sleep))->toBe(500000.0);
    });
});

test('when and unless gate the sleep', function () {
    Sleep::fake();

    // Control test
    Sleep::assertSlept(fn () => true, 0);
    Sleep::for(1)->second();
    Sleep::assertSlept(fn () => true, 1);
    Sleep::fake();
    Sleep::assertSlept(fn () => true, 0);

    // Reset
    Sleep::fake();

    // Will not sleep if `when()` yields `false`
    Sleep::for(1)->second()->when(false);
    Sleep::for(1)->second()->when(fn () => false);

    // Will not sleep if `unless()` yields `true`
    Sleep::for(1)->second()->unless(true);
    Sleep::for(1)->second()->unless(fn () => true);

    // Finish 'do not sleep' tests - assert no sleeping occurred
    Sleep::assertSlept(fn () => true, 0);

    // Will sleep if `when()` yields `true`
    Sleep::for(1)->second()->when(true);
    Sleep::assertSlept(fn () => true, 1);
    Sleep::for(1)->second()->when(fn () => true);
    Sleep::assertSlept(fn () => true, 2);

    // Will sleep if `unless()` yields `false`
    Sleep::for(1)->second()->unless(false);
    Sleep::assertSlept(fn () => true, 3);
    Sleep::for(1)->second()->unless(fn () => false);
    Sleep::assertSlept(fn () => true, 4);
});

describe('whenFakingSleep callbacks', function () {
    test('every registered callback receives the duration', function () {
        $countA = 0;
        $countB = 0;
        Sleep::fake();
        Sleep::whenFakingSleep(function ($duration) use (&$countA) {
            $countA += $duration->totalMilliseconds;
        });
        Sleep::whenFakingSleep(function ($duration) use (&$countB) {
            $countB += $duration->totalMilliseconds;
        });

        Sleep::for(1)->millisecond();
        Sleep::for(2)->millisecond();

        Sleep::assertSequence([
            Sleep::for(1)->millisecond(),
            Sleep::for(2)->millisecond(),
        ]);

        expect((float) $countA)->toBe(3.0)
            ->and((float) $countB)->toBe(3.0);
    });

    test('callbacks do not run when sleeping for real', function () {
        Sleep::whenFakingSleep(function () {
            throw new Exception('Should not run without faking.');
        });

        Sleep::for(1)->millisecond();

        expect(true)->toBeTrue();
    });
});

describe('carbon synchronisation', function () {
    test('faked sleep does not advance Carbon by default', function () {
        Carbon::setTestNow('2000-01-01 00:00:00');
        Sleep::fake();

        Sleep::for(5)->minutes()
            ->and(3)->seconds();

        Sleep::assertSequence([
            Sleep::for(303)->seconds(),
        ]);

        expect(Date::now()->toDateTimeString())->toBe('2000-01-01 00:00:00');
    });

    test('syncWithCarbon advances Carbon', function () {
        Carbon::setTestNow('2000-01-01 00:00:00');
        Sleep::fake();
        Sleep::syncWithCarbon();

        Sleep::for(5)->minutes()
            ->and(3)->seconds();

        Sleep::assertSequence([
            Sleep::for(303)->seconds(),
        ]);

        expect(Date::now()->toDateTimeString())->toBe('2000-01-01 00:05:03');
    });

    test('fake accepts syncWithCarbon directly', function (bool $syncWithCarbon, string $datetime) {
        Carbon::setTestNow('2000-01-01 00:00:00');
        Sleep::fake(syncWithCarbon: $syncWithCarbon);

        Sleep::for(5)->minutes()
            ->and(3)->seconds();

        Sleep::assertSequence([
            Sleep::for(303)->seconds(),
        ]);

        expect(Date::now()->toDateTimeString())->toBe($datetime);
    })->with([
        'synced'     => [true, '2000-01-01 00:05:03'],
        'not synced' => [false, '2000-01-01 00:00:00'],
    ]);

    test('fake does not need to sync with Carbon', function () {
        Carbon::setTestNow('2000-01-01 00:00:00');
        Sleep::fake();

        Sleep::for(5)->minutes()
            ->and(3)->seconds();

        Sleep::assertSequence([
            Sleep::for(303)->seconds(),
        ]);

        expect(Date::now()->toDateTimeString())->toBe('2000-01-01 00:00:00');
    });
});
