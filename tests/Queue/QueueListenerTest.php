<?php

use Voyager\Queue\Listener;
use Voyager\Queue\ListenerOptions;
use Mockery as m;
use Symfony\Component\Process\Process;
use Composer\InstalledVersions;

use function Voyager\NutsAndBolts\computer_binary;
use function Voyager\NutsAndBolts\php_binary;

/**
 * Determine whether the installed symfony/process is at least the given version.
 *
 * Laravel reads this through Testbench, which this port does not depend on.
 */
function queueListenerSymfonyProcessAtLeast(string $version): bool
{
    return version_compare(
        InstalledVersions::getPrettyVersion('symfony/process') ?? '0', $version, '>='
    );
}

test('run process calls process', function () {
    $process = m::mock(Process::class)->makePartial();
    $process->shouldReceive('run')->once();
    $listener = m::mock(Listener::class)->makePartial();
    $listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(false);

    $listener->runProcess($process, 1);
});

test('listener stops when memory is exceeded', function () {
    $process = m::mock(Process::class)->makePartial();
    $process->shouldReceive('run')->once();
    $listener = m::mock(Listener::class)->makePartial();
    $listener->shouldReceive('memoryExceeded')->once()->with(1)->andReturn(true);
    $listener->shouldReceive('stop')->once();

    $listener->runProcess($process, 1);
});

test('make process correctly formats command line', function () {
    $listener = new Listener(__DIR__);
    $options = new ListenerOptions;
    $options->backoff = 1;
    $options->memory = 2;
    $options->timeout = 3;
    $process = $listener->makeProcess('connection', 'queue', $options);
    $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

    if (queueListenerSymfonyProcessAtLeast('7.4.5') && windows_os()) {
        $escapeMsys = '"';
    }

    $computerBinary = computer_binary();

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->getWorkingDirectory())->toEqual(__DIR__)
        ->and($process->getTimeout())->toEqual(3)
        ->and($process->getCommandLine())->toEqual($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}connection{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys}");
});

test('make process correctly formats command line with an environment specified', function () {
    $listener = new Listener(__DIR__);
    $options = new ListenerOptions('default', 'test');
    $options->backoff = 1;
    $options->memory = 2;
    $options->timeout = 3;
    $process = $listener->makeProcess('connection', 'queue', $options);
    $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

    if (queueListenerSymfonyProcessAtLeast('7.4.5') && windows_os()) {
        $escapeMsys = '"';
    }

    $computerBinary = computer_binary();

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->getWorkingDirectory())->toEqual(__DIR__)
        ->and($process->getTimeout())->toEqual(3)
        ->and($process->getCommandLine())->toEqual($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}connection{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys} {$escapeMsys}--env=test{$escapeMsys}");
});

test('make process correctly formats command line when the connection is not specified', function () {
    $listener = new Listener(__DIR__);
    $options = new ListenerOptions('default', 'test');
    $options->backoff = 1;
    $options->memory = 2;
    $options->timeout = 3;
    $process = $listener->makeProcess(null, 'queue', $options);
    $escape = $escapeMsys = '\\' === DIRECTORY_SEPARATOR ? '' : '\'';

    if (queueListenerSymfonyProcessAtLeast('7.4.5') && windows_os()) {
        $escapeMsys = '"';
    }

    $computerBinary = computer_binary();

    expect($process)->toBeInstanceOf(Process::class)
        ->and($process->getWorkingDirectory())->toEqual(__DIR__)
        ->and($process->getTimeout())->toEqual(3)
        ->and($process->getCommandLine())->toEqual($escape.php_binary().$escape." {$escape}{$computerBinary}{$escape} {$escape}queue:work{$escape} {$escape}--once{$escape} {$escapeMsys}--name=default{$escapeMsys} {$escapeMsys}--queue=queue{$escapeMsys} {$escapeMsys}--backoff=1{$escapeMsys} {$escapeMsys}--memory=2{$escapeMsys} {$escapeMsys}--sleep=3{$escapeMsys} {$escapeMsys}--tries=1{$escapeMsys} {$escapeMsys}--env=test{$escapeMsys}");
});
