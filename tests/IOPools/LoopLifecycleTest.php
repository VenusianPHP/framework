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

it('stops on a stop signal as before when no handler takes it', function ($backend) {
    $loop = lifecycleLoop($backend);
    $loop->resource('work', busyWork());
    $loop->onStopSignal(fn (int $signo) => false);

    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGTERM));

    expect($loop->run())->toBe(143);
})->with('lifecycle backends')->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('keeps running when a handler takes the stop signal, until the handler stops it', function ($backend) {
    $loop = lifecycleLoop($backend);
    $loop->resource('work', busyWork());
    $heard = [];
    $after = [];

    $loop->onStopSignal(function (int $signo) use ($loop, &$heard, &$after) {
        $heard[] = $signo;
        $loop->at(0.05, function () use ($loop, &$after) {
            $after[] = 'still running';
            $loop->stop(0);
        });

        return true;
    });
    $loop->at(0.02, fn () => posix_kill(getmypid(), SIGTERM));

    expect($loop->run())->toBe(0)
        ->and($heard)->toBe([SIGTERM])
        ->and($after)->toBe(['still running']);
})->with('lifecycle backends')->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('dies on a second stop signal, as if nobody listened, while a handler has the first', function () {
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    $child = proc_open([PHP_BINARY, '-r', <<<PHP
        require '{$autoload}';
        \$registry = new Voyager\\IOPools\\ResourceRegistry();
        \$loop = new Voyager\\IOPools\\EventLoop(\$registry, new Voyager\\IOPools\\LoopWaiter(\$registry, new Voyager\\IOPools\\Waiter\\StreamSelectWaiterBackend(), 5_000_000), new Voyager\\IOPools\\PromiseEngines\\GuzzlePromiseEngine());
        \$loop->resource('work', new class extends Voyager\\IOPools\\Resources\\Pollable { public function tick(): void {} });
        \$loop->onStopSignal(fn (int \$signo) => true);   // takes it, and never stops the loop
        \$loop->at(0.05, fn () => posix_kill(getmypid(), SIGTERM));
        \$loop->at(0.3, fn () => posix_kill(getmypid(), SIGTERM));
        \$loop->at(3.0, fn () => \$loop->stop(7));
        exit(\$loop->run());
        PHP], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    $deadline = microtime(true) + 10;

    while (($status = proc_get_status($child))['running'] && microtime(true) < $deadline) {
        usleep(20_000);
    }

    proc_close($child);

    expect($status['signaled'])->toBeTrue()
        ->and($status['termsig'])->toBe(SIGTERM);
})->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('gives back the stop-signal handler it found once a run ends', function ($backend, string $ends) {
    $mine = function () {};
    pcntl_signal(SIGTERM, $mine);

    try {
        // Two datasets: Pest leaves the backend factory to us.
        $loop = lifecycleLoop($backend instanceof Closure ? $backend() : $backend);
        $loop->resource('work', busyWork());
        $loop->at(0.02, $ends === 'stopped' ? fn () => $loop->stop(0) : fn () => posix_kill(getmypid(), SIGTERM));
        $loop->run();

        expect(pcntl_signal_get_handler(SIGTERM))->toBe($mine);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
})->with('lifecycle backends')->with(['stopped' => ['stopped'], 'signalled' => ['signalled']])
    ->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');

it('leaves stop signals to the process when told to ignore them', function ($backend) {
    $heard = 0;
    pcntl_async_signals(true);
    $mine = function () use (&$heard) { $heard++; };
    pcntl_signal(SIGTERM, $mine);

    try {
        $loop = lifecycleLoop($backend);
        $loop->ignoreStopSignals();
        $loop->resource('work', busyWork());
        $loop->at(0.02, fn () => posix_kill(getmypid(), SIGTERM));
        $loop->at(0.2, fn () => $loop->stop(0));

        expect($loop->run())->toBe(0)
            ->and($heard)->toBe(1)
            ->and(pcntl_signal_get_handler(SIGTERM))->toBe($mine);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
})->with('lifecycle backends')->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'needs pcntl and posix');
