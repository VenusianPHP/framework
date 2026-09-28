<?php

use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\MailHandler;
use Voyager\IOPools\Resources\Pollable;
use Voyager\Contracts\IOPools\Pumpable;
use Voyager\Contracts\IOPools\LoopResources\Background;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;

/** Every backend this machine can load, as loop factories. */
dataset('lifecycle backends', function () {
    yield 'select' => [fn () => new StreamSelectWaiterBackend()];

    if (extension_loaded('kqueue')) {
        yield 'kqueue' => [fn () => new KqueueWaiterBackend()];
    }

    if (extension_loaded('epoll')) {
        yield 'epoll' => [fn () => new EpollWaiterBackend()];
    }
});

function lifecycleLoop($backend, ?MailHandler $mail_handler = null): EventLoop
{
    $registry = new ResourceRegistry();

    return new EventLoop($registry, new LoopWaiter($registry, $backend, 5_000_000), new GuzzlePromiseEngine(), $mail_handler);
}

/** Keeps every hand-off it gets, one list per turn. */
function recordingMailHandler(): MailHandler
{
    return new class implements MailHandler {
        /** @var list<list<object>> */
        public array $hand_offs = [];

        public function handOff(array $mail, Loop $loop): void
        {
            $this->hand_offs[] = $mail;
        }
    };
}

/** Keeps a run alive, the way any real work would. */
function busyWork(): Pollable
{
    return new class extends Pollable {
        public function tick(): void {}
    };
}

it('stops run() with 130 on SIGINT and 143 on SIGTERM, run after run', function ($backend) {
    $loop = lifecycleLoop($backend);
    $loop->resource('work', busyWork());

    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGINT));
    expect($loop->run())->toBe(130);

    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGTERM));
    expect($loop->run())->toBe(143);
})->with('lifecycle backends')->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('runs the onStop hooks when a signal stops the loop', function ($backend) {
    $loop = lifecycleLoop($backend);
    $stopped = false;

    $loop->resource('work', busyWork());
    $loop->onStop(function () use (&$stopped) { $stopped = true; });
    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGTERM));
    $loop->run();

    expect($stopped)->toBeTrue();
})->with('lifecycle backends')->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('ends a run with nothing to do even though it is listening for stop signals', function ($backend) {
    $started = hrtime(true);

    expect(lifecycleLoop($backend)->run())->toBe(0)
        ->and(hrtime(true) - $started)->toBeLessThan(50_000_000);
})->with('lifecycle backends');

it('never lets a background resource keep run() alive', function () {
    $loop = lifecycleLoop(new StreamSelectWaiterBackend());
    $ticks = 0;

    $loop->resource('watcher', new class($ticks) extends Pollable implements Background {
        public function __construct(private int &$ticks) {}

        public function tick(): void { $this->ticks++; }
    });

    expect($loop->run())->toBe(0)
        ->and($ticks)->toBe(0);
});

it('delivers posted and pumped mail at the end of a turn', function () {
    $handler = recordingMailHandler();
    $loop = lifecycleLoop(new StreamSelectWaiterBackend(), $handler);

    $loop->resource('mailer', new class extends Pollable implements Pumpable {
        private bool $sent = false;

        public function tick(): void {}

        public function pump(): array
        {
            [$sent, $this->sent] = [$this->sent, true];

            return $sent ? [] : [(object) ['from' => 'pump']];
        }
    });

    $loop->post((object) ['from' => 'post']);
    $loop->at(0.02, fn () => $loop->stop());
    $loop->run();

    expect($handler->hand_offs)->toHaveCount(1)
        ->and(array_column($handler->hand_offs[0], 'from'))->toBe(['post', 'pump']);
});

it('keeps mail in the bag through until() and delivers it on the next run', function () {
    $handler = recordingMailHandler();
    $loop = lifecycleLoop(new StreamSelectWaiterBackend(), $handler);

    $loop->post((object) ['from' => 'post']);
    $timer = $loop->at(0.01, fn () => null);
    $loop->until(fn () => $timer->dueAt() === null);

    expect($handler->hand_offs)->toBe([]);

    $loop->at(0.01, fn () => $loop->stop());
    $loop->run();

    expect($handler->hand_offs)->toHaveCount(1)
        ->and(array_column($handler->hand_offs[0], 'from'))->toBe(['post']);
});
