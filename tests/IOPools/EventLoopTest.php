<?php

use Venusian\Tests\IOPools\Fixtures\TestEvent;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\MailCollection;
use Voyager\Contracts\IOPools\Receivable;
use Voyager\Contracts\IOPools\Resumable;
use Voyager\Contracts\IOPools\Sleepable;
use Voyager\Contracts\IOPools\LoopTimer;
use Voyager\Contracts\IOPools\StreamWatchable;
use Voyager\Contracts\IOPools\Tickable;
use Voyager\IOPools\EventLoop;

it('fires a timer once, then stops', function () {
    $loop = new EventLoop;
    $fired = 0;

    $loop->at(0.01, function () use (&$fired, $loop) {
        $fired++;
        $loop->stop();
    });

    $status = $loop->run();

    expect($loop)->toBeInstanceOf(Loop::class)
        ->and($fired)->toBe(1)
        ->and($status)->toBe(0)
        ->and($loop->running())->toBeFalse();
});

it('fires two timers in due order, not registration order', function () {
    $loop = new EventLoop;
    $order = [];

    $loop->at(0.02, function () use (&$order) { $order[] = 'late'; });
    $loop->at(0.01, function () use (&$order) { $order[] = 'early'; });

    $loop->run();

    expect($order)->toBe(['early', 'late']);
});

it('repeats an every() timer until cancelled', function () {
    $loop = new EventLoop;
    $fired = 0;

    $timer = $loop->every(0.005, function () use (&$fired, &$timer) {
        if (++$fired === 3) {
            $timer->cancel();
        }
    }, 'repeat');

    $loop->run();

    expect($fired)->toBe(3)
        ->and($timer->cancelled())->toBeTrue();
});

it('returns the status handed to stop()', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => $loop->stop(7));

    expect($loop->run())->toBe(7);
});

it('ends the run when nothing is scheduled', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => null);

    $start = microtime(true);
    $loop->run();

    expect(microtime(true) - $start)->toBeLessThan(0.05)
        ->and($loop->running())->toBeFalse();
});

it('keeps an every() timer on its grid when the callback is slow', function () {
    $loop = new EventLoop;
    $stamps = [];
    $start = microtime(true);

    $timer = $loop->every(0.016, function () use (&$stamps, &$timer, $start) {
        $stamps[] = microtime(true) - $start;
        usleep(5_000);                              // 5ms of "work" inside a 16ms cadence
        if (count($stamps) === 10) {
            $timer->cancel();
        }
    }, 'grid');

    $loop->run();

    // Tenth firing lands near 160ms; a drifting loop (16+5 per turn) would land near 210ms.
    expect($stamps[9])->toBeGreaterThan(0.150)->toBeLessThan(0.185);
});

it('stops on SIGINT instead of dying', function () {
    $loop = new EventLoop;
    $turns = 0;

    $loop->every(0.005, function () use (&$turns) {
        if (++$turns === 2) {
            posix_kill(posix_getpid(), SIGINT);
        }
    }, 'sigint');

    $status = $loop->run();

    expect($turns)->toBeLessThan(5)
        ->and($status)->toBe(130);
})->skip(! extension_loaded('pcntl') || ! extension_loaded('posix'), 'pcntl/posix not loaded');

it('wakes on a stream instead of waiting for the clock', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($ours, false);

    $loop = new EventLoop;

    $watcher = new class($ours, $loop) implements StreamWatchable {
        public int $ticks = 0;
        public string $read = '';

        public function __construct(private $stream, private Loop $loop) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->ticks++;
            $this->read .= fread($this->stream, 8192);
            $this->loop->stop();
        }
    };

    $loop->resource('pair', $watcher);
    $loop->at(0.5, fn () => null);          // the clock's only reason to wake

    fwrite($theirs, 'x');                   // the doorbell, rung before the run

    $start = microtime(true);
    $loop->run();
    $elapsed = microtime(true) - $start;

    // Woken by the byte: near-instant. Woken only by the clock: half a second.
    expect($watcher->ticks)->toBe(1)
        ->and($watcher->read)->toBe('x')
        ->and($elapsed)->toBeLessThan(0.1);

    fclose($ours);
    fclose($theirs);
});

it('keeps running on a stream alone, with no timers set', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($ours, false);

    $loop = new EventLoop;

    $watcher = new class($ours, $loop) implements StreamWatchable {
        public int $ticks = 0;
        public string $read = '';

        public function __construct(private $stream, private Loop $loop) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->ticks++;
            $this->read .= fread($this->stream, 8192);
            $this->loop->stop();
        }
    };

    $loop->resource('pair', $watcher);      // a doorbell, and no alarm clock at all

    fwrite($theirs, 'x');

    $loop->run();

    // A loop that only counts timers sees an empty notebook and quits before listening.
    expect($watcher->ticks)->toBe(1)
        ->and($watcher->read)->toBe('x');

    fclose($ours);
    fclose($theirs);
});

it('paces a plain tickable at the fallback when nothing else can wake the loop', function () {
    $loop = new EventLoop(tick_budget_ms: 5);

    $ticker = new class($loop) implements Tickable {
        public int $ticks = 0;

        public function __construct(private Loop $loop) {}

        public function tick(): void
        {
            if (++$this->ticks === 4) {
                $this->loop->stop();
            }
        }
    };

    $loop->resource('ticker', $ticker);     // no timers, no streams: only the fallback paces this

    $start = microtime(true);
    $loop->run();
    $elapsed = microtime(true) - $start;

    // Four turns at 5ms: at least ~20ms. Near zero means it spun instead of sleeping.
    expect($ticker->ticks)->toBe(4)
        ->and($elapsed)->toBeGreaterThan(0.015)->toBeLessThan(0.2);
});

it('does not wait forever on a stream while a plain tickable needs checking on', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($ours, false);

    $loop = new EventLoop(tick_budget_ms: 5);

    $watcher = new class($ours, $loop) implements StreamWatchable {
        public string $read = '';

        public function __construct(private $stream, private Loop $loop) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->read .= fread($this->stream, 8192);
            $this->loop->stop();
        }
    };

    // Rings the doorbell itself, but only once it has been ticked three times.
    $ringer = new class($theirs) implements Tickable {
        public int $ticks = 0;

        public function __construct(private $stream) {}

        public function tick(): void
        {
            if (++$this->ticks === 3) {
                fwrite($this->stream, 'x');
            }
        }
    };

    $loop->resource('pair', $watcher);
    $loop->resource('ringer', $ringer);

    $start = microtime(true);
    $loop->run();
    $elapsed = microtime(true) - $start;

    // A select with no timeout would never tick the ringer, and this would hang.
    expect($watcher->read)->toBe('x')
        ->and($ringer->ticks)->toBeGreaterThanOrEqual(3)
        ->and($elapsed)->toBeLessThan(0.2);

    fclose($ours);
    fclose($theirs);
});

it('skips missed beats after a stall instead of bursting to catch up', function () {
    $loop = new EventLoop;
    $stamps = [];
    $start = microtime(true);

    $timer = $loop->every(0.01, function () use (&$stamps, &$timer, $start) {
        $stamps[] = microtime(true) - $start;

        if (count($stamps) === 1) {
            usleep(55_000);                         // stall across five beats
        }

        if (count($stamps) === 3) {
            $timer->cancel();
        }
    }, 'stall');

    $loop->run();

    // Bursting would fire the second within a millisecond of the stall ending and the third
    // right behind it. Skipping puts them a full beat apart, back on the 10ms grid.
    expect($stamps[2] - $stamps[1])->toBeGreaterThan(0.007)
        ->and($stamps[1])->toBeGreaterThan(0.065);
});

it('finishes the turn when a resource throws, then rethrows', function () {
    $loop = new EventLoop(tick_budget_ms: 5);

    $loop->resource('bad', new class implements Tickable {
        public function tick(): void { throw new RuntimeException('boom'); }
    });

    $good = new class implements Tickable {
        public int $ticks = 0;
        public function tick(): void { $this->ticks++; }
    };

    $loop->resource('good', $good);

    expect(fn () => $loop->run())->toThrow(RuntimeException::class, 'boom')
        ->and($good->ticks)->toBe(1);
});

it('still hands off the good mail on a turn that fails', function () {
    $handler = new class implements Receivable {
        public array $names = [];

        public function handOff(MailCollection $mail): void
        {
            $this->names = [...$this->names, ...$mail->mail()->map(fn (TestEvent $e) => $e->name())->values()->all()];
        }
    };

    $loop = new EventLoop(mail_handler: $handler);

    $loop->at(0.001, function () use ($loop) {
        $loop->post(new TestEvent('made-it'));
        throw new RuntimeException('boom');
    });

    expect(fn () => $loop->run())->toThrow(RuntimeException::class, 'boom')
        ->and($handler->names)->toBe(['made-it']);
});

it('hands mail posted from a timer callback to the handler', function () {
    $handler = new class implements Receivable {
        public array $names = [];

        public function handOff(MailCollection $mail): void
        {
            $this->names = [...$this->names, ...$mail->mail()->map(fn (TestEvent $e) => $e->name())->values()->all()];
        }
    };

    $loop = new EventLoop(mail_handler: $handler);

    $loop->at(0.001, fn () => $loop->post(new TestEvent('from-timer')));

    $loop->run();

    expect($handler->names)->toBe(['from-timer']);
});

it('ends the run once a stream resource forgets itself at EOF', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($ours, false);

    $loop = new EventLoop;

    $watcher = new class($ours, $loop) implements StreamWatchable {
        public string $read = '';
        public int $ticks = 0;

        public function __construct(private $stream, private Loop $loop) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->ticks++;
            $this->read .= fread($this->stream, 8192);

            if (feof($this->stream)) {
                $this->loop->forget('pair');
            }
        }
    };

    $loop->resource('pair', $watcher);

    fwrite($theirs, 'bye');
    fclose($theirs);                        // readable forever from here on

    $start = microtime(true);
    $loop->run();                           // no stop(): it ends because nothing is left

    expect($watcher->read)->toBe('bye')
        ->and($watcher->ticks)->toBeLessThan(3)
        ->and(microtime(true) - $start)->toBeLessThan(0.1);

    fclose($ours);
});

it('does not spin on a stream that was closed out from under it', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    $loop = new EventLoop;

    $watcher = new class($ours) implements StreamWatchable {
        public int $ticks = 0;

        public function __construct(private $stream) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->ticks++;
        }
    };

    $loop->resource('dead', $watcher);
    fclose($ours);

    $turns = 0;
    $loop->every(0.005, function () use (&$turns, $loop) {
        if (++$turns === 3) {
            $loop->stop();
        }
    }, 'clock');

    $start = microtime(true);
    $loop->run();

    // A closed handle left in the set makes select throw. Dropped from the set, the loop
    // just keeps its beat on the clock and never reads the dead watcher.
    expect($watcher->ticks)->toBe(0)
        ->and(microtime(true) - $start)->toBeGreaterThan(0.012);

    fclose($theirs);
});

/** A sleeper that records every budget it was handed and sleeps it for real. */
function recordingSleeper(Loop $loop, int $stop_after): Tickable&Sleepable
{
    return new class($loop, $stop_after) implements Tickable, Sleepable {
        public array $budgets = [];
        public int $ticks = 0;

        public function __construct(private Loop $loop, private int $stop_after) {}

        public function sleep(int $budget_ms = 0): array
        {
            $this->budgets[] = $budget_ms;
            usleep($budget_ms * 1_000);

            return [];
        }

        public function tick(): void
        {
            if (++$this->ticks === $this->stop_after) {
                $this->loop->stop();
            }
        }
    };
}

it('gives the sleeper the fallback pace when no alarm is set', function () {
    $loop = new EventLoop(tick_budget_ms: 5);
    $sleeper = recordingSleeper($loop, stop_after: 3);

    $loop->resource('os', $sleeper);
    $loop->run();

    expect($sleeper->budgets)->toBe([5, 5, 5]);
});

it('gives the sleeper the time until the next alarm, not the fallback pace', function () {
    $loop = new EventLoop(tick_budget_ms: 5);
    $sleeper = recordingSleeper($loop, stop_after: 1);

    $loop->resource('os', $sleeper);
    $loop->at(0.03, fn () => null);

    $loop->run();

    expect($sleeper->budgets[0])->toBeGreaterThan(25)->toBeLessThanOrEqual(30);
});

it('lets the sleeper sleep and still reads a stream that rang meanwhile', function () {
    [$ours, $theirs] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_blocking($ours, false);

    $loop = new EventLoop(tick_budget_ms: 5);
    $sleeper = recordingSleeper($loop, stop_after: 100);

    $watcher = new class($ours, $loop) implements StreamWatchable {
        public string $read = '';

        public function __construct(private $stream, private Loop $loop) {}

        public function streams(): array
        {
            return [$this->stream];
        }

        public function tick(): void
        {
            $this->read .= fread($this->stream, 8192);
            $this->loop->stop();
        }
    };

    $loop->resource('os', $sleeper);
    $loop->resource('pair', $watcher);

    fwrite($theirs, 'x');
    $loop->run();

    // The sleeper took the sleep; the stream was picked up by the glance right after it.
    expect($sleeper->budgets)->toBe([5])
        ->and($watcher->read)->toBe('x');

    fclose($ours);
    fclose($theirs);
});

it('until() returns once its condition holds, with no run() around it', function () {
    $loop = new EventLoop;
    $answer = null;

    $loop->at(0.01, function () use (&$answer) { $answer = 'here'; });

    $loop->until(function () use (&$answer) { return ! is_null($answer); });

    expect($answer)->toBe('here')
        ->and($loop->running())->toBeFalse();
});

it('until() keeps other timers firing while it waits', function () {
    $loop = new EventLoop;
    $beats = 0;
    $done = false;

    $loop->every(0.005, function () use (&$beats) { $beats++; }, 'heartbeat');
    $loop->at(0.03, function () use (&$done) { $done = true; });

    $loop->until(function () use (&$done) { return $done; });

    expect($beats)->toBeGreaterThanOrEqual(4);
});

it('until() pumps mail into the bag but never hands it off', function () {
    $handler = new class implements Receivable {
        public array $names = [];

        public function handOff(MailCollection $mail): void
        {
            $this->names = [...$this->names, ...$mail->mail()->map(fn (TestEvent $e) => $e->name())->values()->all()];
        }
    };

    $loop = new EventLoop(mail_handler: $handler);
    $done = false;

    $loop->at(0.001, function () use ($loop, &$done) {
        $loop->post(new TestEvent('held'));
        $done = true;
    });

    $loop->until(function () use (&$done) { return $done; });

    expect($handler->names)->toBe([]);

    // The next loud turn delivers what the quiet ones held back.
    $loop->at(0.001, fn () => null);
    $loop->run();

    expect($handler->names)->toBe(['held']);
});

it('until() inside a run() does not deliver mail in the middle of a callback', function () {
    $handler = new class implements Receivable {
        public array $log = [];

        public function handOff(MailCollection $mail): void
        {
            $this->log[] = 'handoff:'.implode(',', $mail->mail()->map(fn (TestEvent $e) => $e->name())->values()->all());
        }
    };

    $loop = new EventLoop(mail_handler: $handler);

    $loop->at(0.001, function () use ($loop, $handler) {
        $handler->log[] = 'callback:start';

        $answered = false;
        $loop->at(0.005, function () use ($loop, &$answered) {
            $loop->post(new TestEvent('inner'));
            $answered = true;
        });

        $loop->until(function () use (&$answered) { return $answered; });

        $handler->log[] = 'callback:end';
    });

    $loop->run();

    expect($handler->log)->toBe(['callback:start', 'callback:end', 'handoff:inner']);
});

it('until() throws what a resource threw instead of waiting forever', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => throw new RuntimeException('boom'));
    $loop->at(1.0, fn () => null);

    expect(fn () => $loop->until(fn () => false))->toThrow(RuntimeException::class, 'boom');
});

it('until() throws when the loop runs out of work before the condition holds', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => null);        // the only work there is, and it answers nothing

    $start = microtime(true);

    expect(fn () => $loop->until(fn () => false))
        ->toThrow(EventLoopException::class, 'ran out of work')
        ->and(microtime(true) - $start)->toBeLessThan(0.1);
});

it('until() throws on an empty loop instead of spinning', function () {
    expect(fn () => (new EventLoop)->until(fn () => false))
        ->toThrow(EventLoopException::class, 'ran out of work');
});

it('until() on a bare loop resumes a waiting fiber whose condition already holds instead of cancelling it', function () {
    $loop = new EventLoop;
    $flag = false;

    $task = $loop->async(function () use ($loop, &$flag): string {
        $loop->until(function () use (&$flag): bool { return $flag; });

        return 'resumed';
    });
    $flag = true;

    $loop->until(fn (): bool => $task->settled());

    expect($task->wait())->toBe('resumed');
});

it('until() gives up when the loop is stopped underneath it', function () {
    $loop = new EventLoop;
    $caught = null;

    $loop->at(0.001, function () use ($loop, &$caught) {
        $loop->at(0.005, fn () => $loop->stop(130));     // what SIGINT does
        $loop->at(1.0, fn () => null);                   // the answer that never comes

        try {
            $loop->until(fn () => false);
        } catch (EventLoopException $e) {
            $caught = $e->getMessage();
        }
    });

    $start = microtime(true);
    $status = $loop->run();

    expect($caught)->toContain('stopped')
        ->and($status)->toBe(130)
        ->and(microtime(true) - $start)->toBeLessThan(0.2);
});

it('until() still waits after stop() ended a run()', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => $loop->stop());
    $loop->every(1.0, fn () => null, 'keepalive');
    $loop->run();

    $done = false;
    $loop->at(0.001, function () use (&$done) { $done = true; });
    $loop->until(function () use (&$done) { return $done; });

    expect($done)->toBeTrue();
});

it('until() still waits after stop() interrupted an earlier until()', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => $loop->stop());
    $loop->every(1.0, fn () => null, 'keepalive');

    expect(fn () => $loop->until(fn () => false))->toThrow(EventLoopException::class, 'stopped');

    $done = false;
    $loop->at(0.001, function () use (&$done) { $done = true; });
    $loop->until(function () use (&$done) { return $done; });

    expect($done)->toBeTrue();
});

it('a run() after a stopped one starts from status 0', function () {
    $loop = new EventLoop;

    $loop->at(0.001, fn () => $loop->stop(7));
    expect($loop->run())->toBe(7);

    $loop->at(0.001, fn () => null);
    expect($loop->run())->toBe(0);
});

it('registers a resumable through resource(), like any other', function () {
    $loop = new EventLoop;

    $resumable = new class implements Resumable {
        public int $calls = 0;
        public function resume(): bool { $this->calls++; return false; }
    };

    $loop->resource('mine', $resumable);
    $loop->at(0.01, fn () => null);
    $loop->run();

    expect($resumable->calls)->toBeGreaterThan(0);
});
