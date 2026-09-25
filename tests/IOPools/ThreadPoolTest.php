<?php

use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Venusian\Tests\IOPools\Fixtures\AnswerThenDie;
use Venusian\Tests\IOPools\Fixtures\ExitingJob;
use Venusian\Tests\IOPools\Fixtures\ExplodingJob;
use Venusian\Tests\IOPools\Fixtures\HoldsClosure;
use Venusian\Tests\IOPools\Fixtures\ReturnsDate;
use Venusian\Tests\IOPools\Fixtures\SleepFor;
use Voyager\Contracts\IOPools\CancelledException;
use Voyager\Contracts\IOPools\DeadWorkerException;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\Contracts\IOPools\RemoteException;
use Voyager\Contracts\IOPools\StoppedPoolException;
use Voyager\Contracts\IOPools\WorkerPool;
use Voyager\Hashing\HashGig;
use Voyager\Filesystem\DiskGig;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\Pool;
use Voyager\IOPools\ProcessPool;
use Voyager\IOPools\ThreadPool;
use Voyager\Workflows\AsyncParallelBatchNode;
use Voyager\Workflows\Runtimes\LoopRuntime;
use Voyager\Workflows\SharedBag;

it('hands back something usable through the contracts alone', function () {
    $answer = (function (WorkerPool $pool): mixed {
        return $pool->submit(new AddNumbers(2, 3))->wait();
    })($this->pool);

    expect($answer)->toBe(5);
});

it('refuses a pool of nothing', function () {
    expect(fn () => new ProcessPool(new EventLoop, workerCommand(), 0))
        ->toThrow(EventLoopException::class, 'at least one worker');
})->skip(processOnly(), 'process driver only');

it('refuses max_jobs below one', function () {
    expect(fn () => new ProcessPool(new EventLoop, workerCommand(), 2, 0))
        ->toThrow(EventLoopException::class, 'max_jobs');
})->skip(processOnly(), 'process driver only');

it('gives up when a process worker never says hello', function () {
    $loop = new EventLoop;
    $pool = new ProcessPool($loop, [PHP_BINARY, '-r', 'sleep(30);'], 1, null, 0.2);
    $start = microtime(true);

    expect(fn () => $pool->submit(new AddNumbers(2, 3))->wait())
        ->toThrow(EventLoopException::class, 'did not answer')
        ->and(microtime(true) - $start)->toBeLessThan(1.0);

    $pool->shutDown();
})->skip(processOnly(), 'process driver only');

it('refuses a hello timeout of zero', function () {
    expect(fn () => new ProcessPool(new EventLoop, workerCommand(), 1, null, 0.0))
        ->toThrow(EventLoopException::class, 'hello_timeout_s');
})->skip(processOnly(), 'process driver only');

it('rejects at once when a worker writes outside a frame', function (string $code, string $says) {
    $pool = new ProcessPool(new EventLoop, [PHP_BINARY, '-r', $code], 1);
    $start = microtime(true);

    expect(fn () => $pool->submit(new AddNumbers(2, 3))->wait())
        ->toThrow(DeadWorkerException::class, $says)
        ->and(microtime(true) - $start)->toBeLessThan(1.0);          // not the 5s hello timeout, not forever

    $pool->shutDown();
})->with([
    'stray text before the hello' => ['echo "Warning: noise\n"; sleep(30);', 'Warning: noise'],
    'a frame that is not a hello' => ['$b = serialize(["ok" => 1]); echo "VFP\x01".pack("N", strlen($b)).$b; sleep(30);', 'Expected a hello first'],
])->skip(processOnly(), 'process driver only');

it('holds a gig bigger than the pipe until the worker says hello', function () {
    $pool = new ProcessPool(new EventLoop, [PHP_BINARY, '-r', 'sleep(30);'], 1, null, 0.2);
    $start = microtime(true);

    // 1MB: a blocking write into a pipe nobody reads would never return, and the timeout could never fire
    expect(fn () => $pool->submit(new SleepFor(0, str_repeat('x', 1024 * 1024)))->wait())
        ->toThrow(EventLoopException::class, 'did not answer')
        ->and(microtime(true) - $start)->toBeLessThan(1.0);

    $pool->shutDown();
})->skip(processOnly(), 'process driver only');

it('hands the held gig over once a slow boot says hello', function () {
    $pool = new ProcessPool(new EventLoop, workerCommand(), 1);

    expect($pool->submit(new SleepFor(0, str_repeat('x', 1024 * 1024)))->wait())->toHaveLength(1024 * 1024);

    $pool->shutDown();
})->skip(processOnly(), 'process driver only');


function workerCommand(): array
{
    $root = dirname(__DIR__, 2);

    return [
        PHP_BINARY,
        $root.'/src/Voyager/IOPools/bin/pool-worker',
        $root.'/vendor/autoload.php',
        $root,
    ];
}

/** 'process' unless POOL_TEST_DRIVER says otherwise. `composer test:zts` sets it to 'thread'. */
function poolDriver(): string
{
    return getenv('POOL_TEST_DRIVER') ?: 'process';
}

/** For ->skip(): tests that reach for pids, stdio, or SIGKILL. */
function processOnly(): bool
{
    return poolDriver() !== 'process';
}

function pool(EventLoop $loop, int $size = 2, ?int $max_jobs = null): Pool
{
    $root = dirname(__DIR__, 2);

    return poolDriver() === 'thread'
        ? new ThreadPool($loop, $root.'/vendor/autoload.php', $root, $size, $max_jobs, 0.1)
        : new ProcessPool($loop, workerCommand(), $size, $max_jobs);
}

/** Spawn $n workers and run one gig on each, so the clock is measuring jobs, not boot. */
function warm(Pool $pool, int $n): void
{
    $promises = [];

    for ($i = 0; $i < $n; $i++) {
        $promises[] = $pool->submit(new AddNumbers(0, 0));
    }

    foreach ($promises as $promise) {
        $promise->wait();
    }
}

beforeEach(function () {
    $this->loop = new EventLoop;
    $this->pool = pool($this->loop);
});

afterEach(function () {
    $this->pool->shutDown();
});

it('runs a job in another process and resolves with its return', function () {
    $promise = $this->pool->submit(new AddNumbers(2, 3));          // fixture PoolJob

    expect($promise->wait())->toBe(5);
});

it('rejects with RemoteException when the job throws', function () {
    $promise = $this->pool->submit(new ExplodingJob('nope'));

    expect(fn () => $promise->wait())->toThrow(function (RemoteException $e) {
        expect($e->remote_class)->toBe(RuntimeException::class)
            ->and($e->getMessage())->toBe('[RuntimeException] nope')
            ->and($e->remote_trace)->toContain('ExplodingJob');
    });
});

it('runs jobs side by side', function () {
    warm($this->pool, 2);

    $start = microtime(true);

    $a = $this->pool->submit(new SleepFor(0.2));
    $b = $this->pool->submit(new SleepFor(0.2));
    $a->wait(); $b->wait();

    expect(microtime(true) - $start)->toBeLessThan(0.35);        // ~0.2, not 0.4
});

it('queues past its size and still finishes everything', function () {
    $loop = new EventLoop;
    $pool = pool($loop, 2);

    $promises = [];

    foreach (range(1, 5) as $n) {
        $promises[] = $pool->submit(new SleepFor(0.05, $n));
    }

    expect(array_map(fn ($promise) => $promise->wait(), $promises))->toBe([1, 2, 3, 4, 5]);

    $pool->shutDown();
});

it('keeps loop timers firing while jobs run', function () {
    $beats = 0;

    $this->loop->every(0.05, function () use (&$beats) {
        $beats++;
    }, 'heartbeat');

    $this->pool->submit(new SleepFor(0.2))->wait();

    expect($beats)->toBeGreaterThanOrEqual(3);
});

/** True while the process exists. proc_close() reaps, so a closed worker reads as gone. */
function processExists(int $pid): bool
{
    return posix_kill($pid, 0);
}

describe('step 6: death', function () {
    it('rejects with DeadWorkerException when the worker exits mid-gig', function () {
        $promise = $this->pool->submit(new ExitingJob('goodbye cruel world'));

        expect(fn () => $promise->wait())->toThrow(function (DeadWorkerException $e) {
            expect($e->getMessage())->toContain('pool:0')
                ->and($e->getMessage())->toContain('goodbye cruel world');    // the stderr tail
        });
    })->skip(processOnly(), 'process driver only');

    it('keeps serving after a worker dies', function () {
        try {
            $this->pool->submit(new ExitingJob)->wait();
        } catch (DeadWorkerException) {
        }

        expect($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });

    it('respawns for a gig still in line when its worker dies', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1);                                        // one worker: the second gig must queue

        $doomed = $pool->submit(new ExitingJob);
        $queued = $pool->submit(new AddNumbers(1, 2));

        expect($queued->wait())->toBe(3)
            ->and(fn () => $doomed->wait())->toThrow(DeadWorkerException::class);

        $pool->shutDown();
    });

    it('settles a reply from a worker that dies right after writing it', function () {
        $promise = $this->pool->submit(new AnswerThenDie(42));
        [$pid] = $this->pool->pids();

        // The frame and the EOF may land on one tick or two: the parent can wake between the
        // child's write and its exit. Either way the answer is real.
        expect($promise->wait())->toBe(42);

        // Once the child is actually gone, the next hand-out drops it: via tick() if the EOF was
        // seen, or via idle()'s alive() check if the worker went idle first.
        usleep(50_000);

        expect($this->pool->submit(new AddNumbers(1, 1))->wait())->toBe(2)
            ->and($this->pool->pids())->not->toContain($pid);
    })->skip(processOnly(), 'process driver only');

    it('drops a worker that died while idle', function () {
        warm($this->pool, 1);
        [$pid] = $this->pool->pids();

        posix_kill($pid, SIGKILL);                                      // nobody is watching it now
        usleep(50_000);

        expect($this->pool->submit(new AddNumbers(2, 2))->wait())->toBe(4)
            ->and($this->pool->pids())->not->toContain($pid);
    })->skip(processOnly(), 'process driver only');
});

describe('step 7: shutdown', function () {
    it('kills every worker on shutDown', function () {
        warm($this->pool, 2);
        $pids = $this->pool->pids();

        expect($pids)->toHaveCount(2);

        $this->pool->shutDown();

        foreach ($pids as $pid) {
            expect(processExists($pid))->toBeFalse();
        }

        expect($this->pool->pids())->toBe([]);
    })->skip(processOnly(), 'process driver only');

    it('rejects a running gig and a queued gig with StoppedPoolException', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1);

        $running = $pool->submit(new SleepFor(1));
        $queued  = $pool->submit(new AddNumbers(1, 1));

        $pool->shutDown();

        expect(fn () => $running->wait())->toThrow(StoppedPoolException::class)
            ->and(fn () => $queued->wait())->toThrow(StoppedPoolException::class);
    });

    it('throws StoppedPoolException out of a wait() in progress', function () {
        $this->loop->at(0.05, fn () => $this->pool->shutDown());

        expect(fn () => $this->pool->submit(new SleepFor(1))->wait())
            ->toThrow(StoppedPoolException::class);
    });

    it('shuts its workers down when run() ends', function () {
        $sum = null;

        $this->pool->submit(new AddNumbers(1, 1))->then(function ($v) use (&$sum) {
            $sum = $v;
        });
        [$pid] = $this->pool->pids();

        $this->loop->run();                                             // no stop(): ends because nothing is left

        expect($sum)->toBe(2)
            ->and(processExists($pid))->toBeFalse()
            ->and($this->pool->pids())->toBe([]);
    })->skip(processOnly(), 'process driver only');

    it('can be used again after shutDown', function () {
        warm($this->pool, 1);
        $this->pool->shutDown();

        expect($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });

    it('is harmless to shut down twice', function () {
        warm($this->pool, 1);

        $this->pool->shutDown();
        $this->pool->shutDown();

        expect($this->pool->workerCount())->toBe(0);
    });

    it('shuts the pool down when a resource kills the run', function () {
        $this->pool->submit(new SleepFor(1));
        [$pid] = $this->pool->pids();

        $this->loop->resource('bad', new class implements \Voyager\Contracts\IOPools\Tickable {
            public function tick(): void { throw new RuntimeException('boom'); }
        });

        expect(fn () => $this->loop->run())->toThrow(RuntimeException::class, 'boom')
            ->and(processExists($pid))->toBeFalse();
    })->skip(processOnly(), 'process driver only');
});

describe('step 9: recycle', function () {
    it('replaces a worker after max_jobs and still finishes the line', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1, 2);          // one worker, two gigs each

        $seen = [];

        foreach (range(1, 5) as $n) {
            $promise = $pool->submit(new AddNumbers($n, 0));
            $seen = [...$seen, ...$pool->pids()];

            expect($promise->wait())->toBe($n);
        }

        // 5 gigs at 2 each: three different processes did the work
        expect(count(array_unique($seen)))->toBeGreaterThanOrEqual(3);

        $pool->shutDown();
    })->skip(processOnly(), 'process driver only');

    it('never hands a gig to a retiring worker', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1, 1);

        $first  = $pool->submit(new AddNumbers(1, 1))->wait();          // that worker is now retiring
        $second = $pool->submit(new AddNumbers(2, 2))->wait();          // must go to a fresh one

        expect([$first, $second])->toBe([2, 4]);

        $pool->shutDown();
    });

    it('moves the line onto a fresh worker when the last one retires', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1, 1);

        $promises = [];
        foreach (range(1, 3) as $n) {
            $promises[] = $pool->submit(new AddNumbers($n, 0));         // two of these queue
        }

        expect(array_map(fn ($promise) => $promise->wait(), $promises))->toBe([1, 2, 3]);

        $pool->shutDown();
    });

    it('retires by closing stdin, so the worker ends itself', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1, 1);

        $pool->submit(new AddNumbers(1, 1))->wait();
        [$pid] = $pool->pids();

        $loop->run();                                                   // turns until the retiree's EOF lands

        expect(processExists($pid))->toBeFalse()
            ->and($pool->pids())->toBe([]);
    })->skip(processOnly(), 'process driver only');

    it('lets run() end after a retire, with nothing left watched', function () {
        $loop = new EventLoop;
        $pool = pool($loop, 1, 1);
        $sum = null;

        $pool->submit(new AddNumbers(1, 1))->then(function ($v) use (&$sum) { $sum = $v; });

        $loop->run();                                                   // must end by itself

        expect($sum)->toBe(2);
    });

    it('never recycles when max_jobs is null', function () {
        warm($this->pool, 1);
        [$pid] = $this->pool->pids();

        foreach (range(1, 4) as $n) {
            $this->pool->submit(new AddNumbers($n, 0))->wait();
        }

        expect($this->pool->pids())->toBe([$pid]);
    })->skip(processOnly(), 'process driver only');
});

describe('payloads', function () {
    it('rejects the promise, not submit(), when a gig cannot be sent', function () {
        $promise = $this->pool->submit(new HoldsClosure(fn () => 1));

        expect(fn () => $promise->wait())->toThrow(EventLoopException::class, "can't be sent to a worker");
    });

    it('keeps serving after refusing a gig', function () {
        $this->pool->submit(new HoldsClosure(fn () => 1));

        expect($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });

    it('carries internal classes both ways', function () {
        $answer = $this->pool->submit(new ReturnsDate(new DateTimeImmutable('2026-01-01')))->wait();

        expect($answer)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($answer->format('Y'))->toBe('2027');
    });
});

describe('warm', function () {
    it('spawns workers ahead of any gig, capped at size', function () {
        $this->pool->warm(10);                          // pool() builds size 2

        expect($this->pool->workerCount())->toBe(2);
    });

    it('is idempotent', function () {
        $this->pool->warm(1);
        $this->pool->warm(1);

        expect($this->pool->workerCount())->toBe(1);
    });

    it('leaves warm workers unwatched, so run() still ends', function () {
        $this->pool->warm(2);

        $start = microtime(true);
        $this->loop->run();

        expect(microtime(true) - $start)->toBeLessThan(0.5);
    });

    it('hands the first gig to a warm worker instead of spawning', function () {
        $this->pool->warm(1);

        expect($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5)
            ->and($this->pool->workerCount())->toBe(1);
    });
});

describe('async', function () {
    it('overlaps gigs waited on from separate fibers', function () {
        warm($this->pool, 2);
        $start = microtime(true);

        $a = $this->loop->async(fn () => $this->pool->submit(new SleepFor(0.1, 'a'))->wait());
        $b = $this->loop->async(fn () => $this->pool->submit(new SleepFor(0.1, 'b'))->wait());

        expect($a->wait().$b->wait())->toBe('ab')
            ->and(microtime(true) - $start)->toBeLessThan(0.18);
    });

    it('keeps a timer firing while a fiber waits on a gig inside run()', function () {
        warm($this->pool, 1);
        $beats = 0;
        $answer = null;

        $this->loop->async(function () use (&$answer) {
            $answer = $this->pool->submit(new SleepFor(0.1, 'done'))->wait();
        });
        $ticker = $this->loop->every(0.02, function () use (&$beats, &$ticker) {
            if (++$beats === 3) $ticker->cancel();
        }, 'beats');

        $this->loop->run();

        expect($answer)->toBe('done')
            ->and($beats)->toBe(3);
    });

    it('a cancelled fiber leaves its gig to finish; the pool keeps serving', function () {
        warm($this->pool, 1);

        $task = $this->loop->async(fn () => $this->pool->submit(new SleepFor(0.05))->wait());
        $task->cancel();

        expect(fn () => $task->wait())->toThrow(CancelledException::class)
            ->and($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });
});

describe('hashing', function () {
    it('hashes in a worker and the main thread can check it', function () {
        $hash = $this->pool->submit(new HashGig('secret', 'bcrypt', ['rounds' => 4]))->wait();

        expect(password_verify('secret', $hash))->toBeTrue();
    });
});

describe('filesystem', function () {
    it('writes and reads a disk file in a worker', function () {
        $root = dirname(__DIR__, 2);                                      // the pool worker boots this repo as its app
        $path = 'vf-pool-'.getmypid().'.txt';

        $this->pool->submit(new DiskGig('local', 'put', [$path, 'from a worker']))->wait();

        expect($this->pool->submit(new DiskGig('local', 'get', [$path]))->wait())->toBe('from a worker');

        $this->pool->submit(new DiskGig('local', 'delete', [$path]))->wait();
    });
});

describe('workflows', function () {
    it('fans a parallel batch node out over the pool, gated by concurrency', function () {
        warm($this->pool, 2);
        $pool = $this->pool;

        $node = new class(1, 0, 2, new LoopRuntime($this->loop)) extends AsyncParallelBatchNode {
            public $pool;
            public function prepAsync(SharedBag $shared): mixed { return [0.05, 0.05, 0.05, 0.05]; }
            public function execAsync(mixed $seconds): mixed { return $this->pool->submit(new SleepFor($seconds, $seconds))->wait(); }
            public function postAsync(SharedBag $shared, mixed $prepRes, mixed $execRes): mixed { $shared->results = $execRes; return null; }
        };
        $node->pool = $pool;

        $start = microtime(true);
        $node->runAsync($shared = new SharedBag);
        $elapsed = microtime(true) - $start;

        expect($shared->results)->toBe([0.05, 0.05, 0.05, 0.05])
            ->and($elapsed)->toBeGreaterThan(0.09)          // 4 gigs, 2 at a time, 2 workers: two rounds
            ->and($elapsed)->toBeLessThan(0.18);           // serial would be ≥ 0.20
    });
});
