<?php

use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\System\Testing\Concerns\InteractsWithTime;

uses(InteractsWithTime::class);

afterEach(function () {
    Carbon::setTestNow();
});

describe('freezeTime', function () {
    test('it returns the frozen time when given no callback', function () {
        $actual = $this->freezeTime();

        expect(Carbon::hasTestNow())->toBeTrue()
            ->and($actual)->toBeInstanceOf(DateTimeInterface::class)
            ->and(Carbon::getTestNow()->eq($actual))->toBeTrue();
    });

    test('it returns the callback result and thaws afterwards', function () {
        $actual = $this->freezeTime(fn () => 12345);

        expect($actual)->toBe(12345)
            ->and(Carbon::hasTestNow())->toBeFalse();
    });

    test('it returns the callback result even when that is null', function () {
        $actual = $this->freezeTime(fn () => null);

        expect($actual)->toBeNull()
            ->and(Carbon::hasTestNow())->toBeFalse();
    });
});

describe('freezeSecond', function () {
    test('it returns the frozen time, rounded to the second', function () {
        $actual = $this->freezeSecond();

        expect(Carbon::hasTestNow())->toBeTrue()
            ->and($actual)->toBeInstanceOf(DateTimeInterface::class)
            ->and(Carbon::getTestNow()->eq($actual))->toBeTrue()
            ->and($actual->milliseconds)->toBe(0);
    });

    test('it returns the callback result and thaws afterwards', function () {
        $actual = $this->freezeSecond(fn () => 12345);

        expect($actual)->toBe(12345)
            ->and(Carbon::hasTestNow())->toBeFalse();
    });

    test('it returns the callback result even when that is null', function () {
        $actual = $this->freezeSecond(fn () => null);

        expect($actual)->toBeNull()
            ->and(Carbon::hasTestNow())->toBeFalse();
    });
});
