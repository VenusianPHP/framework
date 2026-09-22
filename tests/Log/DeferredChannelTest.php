<?php

use Monolog\Handler\TestHandler;
use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\IOPools\EventLoop;
use Voyager\Log\LogManager;
use Voyager\Signals\SignalDispatcher;

function deferredLogApp(EventLoop $loop): array
{
    $sink = new TestHandler;
    $app = new RenderedInstance(sys_get_temp_dir());
    $app->registerInstance('config', new Repository(['logging' => [
        'default' => 'later',
        'channels' => [
            'now'   => ['driver' => 'monolog', 'handler' => TestHandler::class],
            'later' => ['driver' => 'deferred', 'channel' => 'now'],
        ],
    ]]));
    $app->registerInstance('signals', new SignalDispatcher($app));
    $app->registerInstance(\Voyager\Contracts\IOPools\Loop::class, $loop);

    $manager = new LogManager($app);
    $manager->extend('monolog', fn () => new \Monolog\Logger('now', [$sink]));   // one shared sink we can read

    return [$manager, $sink];
}

it('writes nothing in the caller\'s turn and everything on the next', function () {
    $loop = new EventLoop;
    [$log, $sink] = deferredLogApp($loop);

    $log->info('one');
    $log->info('two');

    expect($sink->getRecords())->toBe([]);

    $loop->at(0.001, fn () => null);            // one turn
    $loop->run();

    expect(array_map(fn ($r) => $r->message, $sink->getRecords()))->toBe(['one', 'two']);
});

it('flushes when the loop stops', function () {
    $loop = new EventLoop;
    [$log, $sink] = deferredLogApp($loop);

    $loop->at(0.001, function () use ($log, $loop) { $log->info('last words'); $loop->stop(); });
    $loop->run();

    expect($sink->hasInfo('last words'))->toBeTrue();
});

it('does not keep run() alive after its flush', function () {
    $loop = new EventLoop;
    [$log] = deferredLogApp($loop);
    $log->info('x');

    $start = microtime(true);
    $loop->run();

    expect(microtime(true) - $start)->toBeLessThan(0.1);
});
