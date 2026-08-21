<?php

use Tests\Console\Fixtures\ConsoleCommandStub;
use Tests\Console\Fixtures\FooBarCommand;
use Voyager\Console\Application;
use Voyager\Console\Scheduling\CacheEventMutex;
use Voyager\Console\Scheduling\CacheSchedulingMutex;
use Voyager\Console\Scheduling\EventMutex;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Console\Scheduling\SchedulingMutex;
use Voyager\Vessel\Vessel;

/** The Schedule bound on the container for the current test. */
function scheduleOnContainer(): Schedule
{
    return Vessel::getInstance()->make(Schedule::class);
}

/** The command line prefix every scheduled computer command carries. */
function computerCommandPrefix(): string
{
    return Application::phpBinary().' '.Application::computerBinary();
}

beforeEach(function () {
    $vessel = Vessel::getInstance();

    $vessel->instance(EventMutex::class, Mockery::mock(CacheEventMutex::class));

    $vessel->instance(SchedulingMutex::class, Mockery::mock(CacheSchedulingMutex::class));

    $vessel->instance(Schedule::class, new Schedule(Mockery::mock(EventMutex::class)));
});

test('useCache passes the store on to both mutexes', function () {
    Vessel::getInstance()->make(EventMutex::class)->shouldReceive('useStore')->once()->with('test');
    Vessel::getInstance()->make(SchedulingMutex::class)->shouldReceive('useStore')->once()->with('test');

    scheduleOnContainer()->useCache('test');
});

test('exec builds a command line, escaping every parameter shape', function () {
    $escape = '\\' === DIRECTORY_SEPARATOR ? '"' : '\'';
    $escapeReal = '\\' === DIRECTORY_SEPARATOR ? '\\"' : '"';

    $schedule = scheduleOnContainer();
    $schedule->exec('path/to/command');
    $schedule->exec('path/to/command -f --foo="bar"');
    $schedule->exec('path/to/command', ['-f']);
    $schedule->exec('path/to/command', ['--foo' => 'bar']);
    $schedule->exec('path/to/command', ['-f', '--foo' => 'bar']);
    $schedule->exec('path/to/command', ['--title' => 'A "real" test']);
    $schedule->exec('path/to/command', [['one', 'two']]);
    $schedule->exec('path/to/command', ['-1 minute']);
    $schedule->exec('path/to/command', ['foo' => ['bar', 'baz']]);
    $schedule->exec('path/to/command', ['--foo' => ['bar', 'baz']]);
    $schedule->exec('path/to/command', ['-F' => ['bar', 'baz']]);

    $events = $schedule->events();

    expect($events[0]->command)->toBe('path/to/command')
        ->and($events[1]->command)->toBe('path/to/command -f --foo="bar"')
        ->and($events[2]->command)->toBe('path/to/command -f')
        ->and($events[3]->command)->toBe("path/to/command --foo={$escape}bar{$escape}")
        ->and($events[4]->command)->toBe("path/to/command -f --foo={$escape}bar{$escape}")
        ->and($events[5]->command)->toBe("path/to/command --title={$escape}A {$escapeReal}real{$escapeReal} test{$escape}")
        ->and($events[6]->command)->toBe("path/to/command {$escape}one{$escape} {$escape}two{$escape}")
        ->and($events[7]->command)->toBe("path/to/command {$escape}-1 minute{$escape}")
        ->and($events[8]->command)->toBe("path/to/command {$escape}bar{$escape} {$escape}baz{$escape}")
        ->and($events[9]->command)->toBe("path/to/command --foo={$escape}bar{$escape} --foo={$escape}baz{$escape}")
        ->and($events[10]->command)->toBe("path/to/command -F {$escape}bar{$escape} -F {$escape}baz{$escape}");
});

test('exec stamps the schedule timezone on the event', function ($timezone) {
    $schedule = new Schedule($timezone);
    $schedule->exec('path/to/command');

    expect($schedule->events()[0]->timezone)->toBe($timezone);
})->with([
    'UTC' => ['UTC'],
    'Asia/Tokyo' => ['Asia/Tokyo'],
]);

test('command builds a computer command line', function () {
    $schedule = scheduleOnContainer();
    $schedule->command('queue:listen');
    $schedule->command('queue:listen --tries=3');
    $schedule->command('queue:listen', ['--tries' => 3]);

    $events = $schedule->events();

    expect($events[0]->command)->toEqual(computerCommandPrefix().' queue:listen')
        ->and($events[1]->command)->toEqual(computerCommandPrefix().' queue:listen --tries=3')
        ->and($events[2]->command)->toEqual(computerCommandPrefix().' queue:listen --tries=3');
});

test('command accepts a command class name', function () {
    $schedule = scheduleOnContainer();
    $schedule->command(ConsoleCommandStub::class, ['--force']);

    expect($schedule->events()[0]->command)->toEqual(computerCommandPrefix().' foo:bar --force');
});

test('command accepts a command instance', function () {
    $schedule = scheduleOnContainer();
    $schedule->command(new FooBarCommand, ['--force']);

    expect($schedule->events()[0]->command)->toEqual(computerCommandPrefix().' foo:bar --force');
});

test('the command description becomes the event description', function () {
    $event = scheduleOnContainer()->command(ConsoleCommandStub::class);

    expect($event->description)->toBe('This is a description about the command');
});

test('the event description can be overwritten', function () {
    $event = scheduleOnContainer()->command(ConsoleCommandStub::class)
        ->description('This is an alternative description');

    expect($event->description)->toBe('This is an alternative description');
});

test('call stamps the schedule timezone on the event', function ($timezone) {
    $schedule = new Schedule($timezone);
    $schedule->call('path/to/command');

    expect($schedule->events()[0]->timezone)->toBe($timezone);
})->with([
    'UTC' => ['UTC'],
    'Asia/Tokyo' => ['Asia/Tokyo'],
]);
