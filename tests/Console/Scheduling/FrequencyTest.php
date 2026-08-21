<?php

use Voyager\Console\Scheduling\Event;
use Voyager\Console\Scheduling\EventMutex;
use Voyager\NutsAndBolts\DataObjects\Carbon;

/** A bare `php foo` event with a mocked mutex. */
function frequencyEvent(): Event
{
    return new Event(Mockery::mock(EventMutex::class), 'php foo');
}

describe('minutes', function () {
    test('an event runs every minute by default and after everyMinute', function () {
        $event = frequencyEvent();

        expect($event->getExpression())->toBe('* * * * *')
            ->and($event->everyMinute()->getExpression())->toBe('* * * * *');
    });

    test('every N minutes', function ($method, $expression) {
        expect(frequencyEvent()->{$method}()->getExpression())->toBe($expression);
    })->with([
        'two' => ['everyTwoMinutes', '*/2 * * * *'],
        'three' => ['everyThreeMinutes', '*/3 * * * *'],
        'four' => ['everyFourMinutes', '*/4 * * * *'],
        'five' => ['everyFiveMinutes', '*/5 * * * *'],
        'ten' => ['everyTenMinutes', '*/10 * * * *'],
        'fifteen' => ['everyFifteenMinutes', '*/15 * * * *'],
        'thirty' => ['everyThirtyMinutes', '*/30 * * * *'],
    ]);
});

describe('hourly', function () {
    test('hourly overrides a finer frequency', function () {
        expect(frequencyEvent()->everyFiveMinutes()->hourly()->getExpression())->toBe('0 * * * *');
    });

    test('hourlyAt accepts a minute, a step, or a list', function ($minutes, $expression) {
        expect(frequencyEvent()->hourlyAt($minutes)->getExpression())->toBe($expression);
    })->with([
        'a minute' => [37, '37 * * * *'],
        'a step' => ['*/10', '*/10 * * * *'],
        'a list' => [[15, 30, 45], '15,30,45 * * * *'],
    ]);

    test('every N hours, at the given minutes', function ($method, $hours) {
        expect(frequencyEvent()->{$method}()->getExpression())->toBe("0 {$hours} * * *")
            ->and(frequencyEvent()->{$method}(37)->getExpression())->toBe("37 {$hours} * * *")
            ->and(frequencyEvent()->{$method}('*/10')->getExpression())->toBe("*/10 {$hours} * * *")
            ->and(frequencyEvent()->{$method}([15, 30, 45])->getExpression())->toBe("15,30,45 {$hours} * * *");
    })->with([
        'odd' => ['everyOddHour', '1-23/2'],
        'two' => ['everyTwoHours', '*/2'],
        'three' => ['everyThreeHours', '*/3'],
        'four' => ['everyFourHours', '*/4'],
        'six' => ['everySixHours', '*/6'],
    ]);
});

describe('daily', function () {
    test('daily runs at midnight', function () {
        expect(frequencyEvent()->daily()->getExpression())->toBe('0 0 * * *');
    });

    test('dailyAt takes a time of day', function () {
        expect(frequencyEvent()->dailyAt('13:08')->getExpression())->toBe('8 13 * * *');
    });

    test('dailyAt parses the minutes and ignores any seconds', function () {
        expect(frequencyEvent()->dailyAt('13:08:10')->getExpression())->toBe('8 13 * * *');
    });

    test('twiceDaily takes two hours', function () {
        expect(frequencyEvent()->twiceDaily(3, 15)->getExpression())->toBe('0 3,15 * * *');
    });

    test('twiceDailyAt takes two hours and a minute', function () {
        expect(frequencyEvent()->twiceDailyAt(3, 15, 5)->getExpression())->toBe('5 3,15 * * *');
    });
});

describe('weekly', function () {
    test('weekly runs on sunday at midnight', function () {
        expect(frequencyEvent()->weekly()->getExpression())->toBe('0 0 * * 0');
    });

    test('weeklyOn takes a day and a time', function () {
        expect(frequencyEvent()->weeklyOn(1, '8:00')->getExpression())->toBe('0 8 * * 1');
    });
});

describe('monthly', function () {
    test('monthly runs on the first at midnight', function () {
        expect(frequencyEvent()->monthly()->getExpression())->toBe('0 0 1 * *');
    });

    test('monthlyOn takes a day and a time', function () {
        expect(frequencyEvent()->monthlyOn(4, '15:00')->getExpression())->toBe('0 15 4 * *');
    });

    test('monthlyOn keeps the minutes of the given time', function () {
        expect(frequencyEvent()->monthlyOn(4, '15:15')->getExpression())->toBe('15 15 4 * *');
    });

    test('lastDayOfMonth resolves against the current month', function () {
        Carbon::setTestNow('2020-10-10 10:10:10');

        expect(frequencyEvent()->lastDayOfMonth()->getExpression())->toBe('0 0 31 * *');

        Carbon::setTestNow(null);
    });

    test('twiceMonthly takes two days', function () {
        expect(frequencyEvent()->twiceMonthly(1, 16)->getExpression())->toBe('0 0 1,16 * *');
    });

    test('twiceMonthly takes two days and a time', function () {
        expect(frequencyEvent()->twiceMonthly(1, 16, '1:30')->getExpression())->toBe('30 1 1,16 * *');
    });
});

describe('days of the week', function () {
    test('weekdays chained with daily', function () {
        expect(frequencyEvent()->weekdays()->daily()->getExpression())->toBe('0 0 * * 1-5');
    });

    test('weekdays chained with hourly', function () {
        expect(frequencyEvent()->weekdays()->hourly()->getExpression())->toBe('0 * * * 1-5');
    });

    test('a day selector sets the day of week field', function ($method, $expression) {
        expect(frequencyEvent()->{$method}()->getExpression())->toBe($expression);
    })->with([
        'weekdays' => ['weekdays', '* * * * 1-5'],
        'weekends' => ['weekends', '* * * * 6,0'],
        'sundays' => ['sundays', '* * * * 0'],
        'mondays' => ['mondays', '* * * * 1'],
        'tuesdays' => ['tuesdays', '* * * * 2'],
        'wednesdays' => ['wednesdays', '* * * * 3'],
        'thursdays' => ['thursdays', '* * * * 4'],
        'fridays' => ['fridays', '* * * * 5'],
        'saturdays' => ['saturdays', '* * * * 6'],
    ]);
});

describe('quarterly and yearly', function () {
    test('quarterly runs on the first of every third month', function () {
        expect(frequencyEvent()->quarterly()->getExpression())->toBe('0 0 1 1-12/3 *');
    });

    test('yearly runs on new year', function () {
        expect(frequencyEvent()->yearly()->getExpression())->toBe('0 0 1 1 *');
    });

    test('yearlyOn takes a month, a day and a time', function () {
        expect(frequencyEvent()->yearlyOn(4, 5, '15:08')->getExpression())->toBe('8 15 5 4 *');
    });

    test('yearlyOn keeps a day of week set beforehand', function () {
        expect(frequencyEvent()->mondays()->yearlyOn(7, '*', '09:01')->getExpression())->toBe('1 9 * 7 1');
    });

    test('yearlyOn combines a day of week with a day of month', function () {
        expect(frequencyEvent()->tuesdays()->yearlyOn(7, 20, '09:01')->getExpression())->toBe('1 9 20 7 2');
    });
});

test('a frequency macro can splice into the expression', function () {
    Event::macro('everyXMinutes', function ($x) {
        return $this->spliceIntoPosition(1, "*/{$x}");
    });

    expect(frequencyEvent()->everyXMinutes(6)->getExpression())->toBe('*/6 * * * *');
});
