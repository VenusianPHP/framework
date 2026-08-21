<?php

use Voyager\Console\Scheduling\Event;
use Voyager\Console\Scheduling\EventMutex;
use Voyager\NutsAndBolts\DataObjects\Str;

use function Voyager\NutsAndBolts\php_binary;

/** A bare `php -i` event with a mocked mutex. */
function schedulingEvent(): Event
{
    return new Event(Mockery::mock(EventMutex::class), 'php -i');
}

/** The mutex-derived schedule id `php -i` hashes to. */
function schedulingEventId(): string
{
    return '"framework'.DIRECTORY_SEPARATOR.'schedule-eeb46c93d45e928d62aaf684d727e213b7094822"';
}

describe('buildCommand', function () {
    test('redirects output to /dev/null on unix', function () {
        expect(schedulingEvent()->buildCommand())->toBe("php -i > '/dev/null' 2>&1");
    })->skipOnWindows();

    test('redirects output to NUL on windows', function () {
        expect(schedulingEvent()->buildCommand())->toBe('php -i > "NUL" 2>&1');
    })->onlyOnWindows();

    test('backgrounds the command and reports back on unix', function () {
        $event = schedulingEvent();
        $event->runInBackground();

        expect($event->buildCommand())->toBe(
            "(php -i > '/dev/null' 2>&1 ; '".php_binary()."' 'computer' schedule:finish ".schedulingEventId()." \"$?\") > '/dev/null' 2>&1 &"
        );
    })->skipOnWindows();

    test('backgrounds the command and reports back on windows', function () {
        $event = schedulingEvent();
        $event->runInBackground();

        expect($event->buildCommand())->toBe(
            'start /b cmd /v:on /c "(php -i & '.php_binary().' computer schedule:finish '.schedulingEventId().' ^!ERRORLEVEL^!) > "NUL" 2>&1"'
        );
    })->onlyOnWindows();

    test('sendOutputTo quotes the destination', function ($destination) {
        $quote = (DIRECTORY_SEPARATOR === '\\') ? '"' : "'";

        $event = schedulingEvent();
        $event->sendOutputTo($destination);

        expect($event->buildCommand())->toBe("php -i > {$quote}{$destination}{$quote} 2>&1");
    })->with([
        'a plain path' => ['/dev/null'],
        'a path with a space' => ['/my folder/foo.log'],
    ]);

    test('appendOutputTo appends rather than truncates', function () {
        $quote = (DIRECTORY_SEPARATOR === '\\') ? '"' : "'";

        $event = schedulingEvent();
        $event->appendOutputTo('/dev/null');

        expect($event->buildCommand())->toBe("php -i >> {$quote}/dev/null{$quote} 2>&1");
    });
});

test('nextRunDate reflects the configured time', function () {
    $event = schedulingEvent();
    $event->dailyAt('10:15');

    expect($event->nextRunDate()->toTimeString())->toBe('10:15:00');
});

test('the mutex name is derived from the command, or from a custom callback', function () {
    $event = schedulingEvent();
    $event->description('Fancy command description');

    expect($event->mutexName())->toBe('framework'.DIRECTORY_SEPARATOR.'schedule-eeb46c93d45e928d62aaf684d727e213b7094822');

    $event->createMutexNameUsing(fn (Event $event) => Str::slug($event->description));

    expect($event->mutexName())->toBe('fancy-command-description');
});

test('daysOfMonth accepts a variadic list or an array', function ($days, $expression) {
    $event = schedulingEvent();

    $event->daysOfMonth(...$days);

    expect($event->getExpression())->toBe($expression);
})->with([
    'variadic' => [[1, 15], '0 0 1,15 * *'],
    'array' => [[[1, 10, 20, 30]], '0 0 1,10,20,30 * *'],
]);
