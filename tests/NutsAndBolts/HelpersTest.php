<?php

use Carbon\CarbonInterval;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\HigherOrderTapProxy;

describe('collections', function () {
    test('collect builds a collection', function () {
        expect(collect([1, 2]))->toBeInstanceOf(Collection::class);
    });

    test('head and last read the ends of an array', function () {
        expect(head([1, 2, 3]))->toBe(1)
            ->and(last([1, 2, 3]))->toBe(3);
    });
});

describe('data access', function () {
    test('data_get reads dot paths', function () {
        expect(data_get(['a' => ['b' => 1]], 'a.b'))->toBe(1)
            ->and(data_get([], 'missing', 'default'))->toBe('default');
    });

    test('data_get supports wildcards', function () {
        expect(data_get([['n' => 'a'], ['n' => 'b']], '*.n'))->toBe(['a', 'b']);
    });

    test('data_set and data_fill write dot paths', function () {
        $target = [];
        data_set($target, 'a.b', 1);
        data_fill($target, 'a.b', 2);
        data_fill($target, 'a.c', 3);

        expect($target)->toBe(['a' => ['b' => 1, 'c' => 3]]);
    });

    test('data_has and data_forget query and prune', function () {
        $target = ['a' => ['b' => 1]];

        expect(data_has($target, 'a.b'))->toBeTrue();

        data_forget($target, 'a.b');

        expect(data_has($target, 'a.b'))->toBeFalse();
    });
});

describe('control flow', function () {
    test('value resolves closures and passes other values through', function () {
        expect(value(1))->toBe(1)
            ->and(value(fn () => 2))->toBe(2)
            ->and(value(fn (int $n) => $n * 2, 3))->toBe(6);
    });

    test('with optionally pipes through a callback', function () {
        expect(with(5))->toBe(5)
            ->and(with(5, fn (int $n) => $n + 1))->toBe(6);
    });

    test('when returns the value only for truthy conditions', function () {
        expect(when(true, 'yes', 'no'))->toBe('yes')
            ->and(when(false, 'yes', 'no'))->toBe('no');
    });

    test('tap runs a side effect and returns the original value', function () {
        $object = new stdClass;
        $object->count = 0;

        $returned = tap($object, function (stdClass $o) {
            $o->count++;
        });

        expect($returned)->toBe($object)
            ->and($object->count)->toBe(1);
    });

    test('tap without a callback returns a proxy', function () {
        expect(tap(new stdClass))->toBeInstanceOf(HigherOrderTapProxy::class);
    });
});

describe('classes', function () {
    test('class_basename strips the namespace', function () {
        expect(class_basename(Collection::class))->toBe('Collection')
            ->and(class_basename(new stdClass))->toBe('stdClass');
    });

    test('class_uses_recursive walks the hierarchy', function () {
        expect(class_uses_recursive(Collection::class))
            ->toContain(Voyager\NutsAndBolts\Concerns\EnumeratesValues::class);
    });
});

describe('environment', function () {
    test('env reads from the environment with a default', function () {
        putenv('VENUSIAN_TEST_KEY=present');

        expect(env('VENUSIAN_TEST_KEY'))->toBe('present')
            ->and(env('VENUSIAN_TEST_MISSING', 'fallback'))->toBe('fallback');

        putenv('VENUSIAN_TEST_KEY');
    });

    test('windows_os reports the platform', function () {
        expect(windows_os())->toBe(PHP_OS_FAMILY === 'Windows');
    });
});

describe('time', function () {
    test('now returns a Carbon instance', function () {
        expect(now())->toBeInstanceOf(Carbon::class);
    });

    test('now accepts a timezone', function () {
        expect(now('UTC')->timezoneName)->toBe('UTC');
    });

    test('interval helpers build CarbonIntervals', function (string $helper, int $value, string $unit, float $expected) {
        $interval = $helper($value);

        expect($interval)->toBeInstanceOf(CarbonInterval::class)
            ->and($interval->{$unit})->toBe($expected);
    })->with([
        'seconds' => ['seconds', 30, 'totalSeconds', 30.0],
        'minutes' => ['minutes', 5, 'totalMinutes', 5.0],
        'hours'   => ['hours', 2, 'totalHours', 2.0],
        'days'    => ['days', 3, 'totalDays', 3.0],
        'weeks'   => ['weeks', 1, 'totalWeeks', 1.0],
    ]);
});
