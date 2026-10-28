<?php

use Voyager\IOPools\WorkerPools\Thread\ThreadSupport;

/*
 * A process's signals belong to one owner. Loops running in threads of the process must leave them
 * alone, or a thread's loop takes SIGTERM from the thread that does own it.
 */

it('leaves SIGTERM to the process\'s own handler while loops in two threads run, ignoring stop signals', function () {
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    $heard = 0;
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$heard) { $heard++; });

    $body = static function (string $autoload): int {
        require_once $autoload;
        $registry = new Voyager\IOPools\ResourceRegistry();
        $backend = extension_loaded('kqueue') ? new Voyager\IOPools\Waiter\KqueueWaiterBackend() : new Voyager\IOPools\Waiter\StreamSelectWaiterBackend();
        $loop = new Voyager\IOPools\EventLoop($registry, new Voyager\IOPools\LoopWaiter($registry, $backend, 5_000_000), new Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine());
        $loop->ignoreStopSignals();
        $loop->every(0.05, fn () => null, 'work');
        $loop->at(1.0, fn () => $loop->stop(0));

        return $loop->run();
    };

    try {
        $futures = [];

        foreach ([1, 2] as $_) {
            $futures[] = (new parallel\Runtime($autoload))->run($body, [$autoload]);
        }

        usleep(400_000);
        posix_kill(getmypid(), SIGTERM);
        usleep(200_000);

        expect($heard)->toBe(1)
            ->and(array_map(fn ($future) => $future->value(), $futures))->toBe([0, 0]);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
})->skip(! ThreadSupport::probe()->supported, 'needs threads: '.ThreadSupport::probe()->reason);
