<?php

use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Venusian\Tests\IOPools\Fixtures\ExitingJob;
use Venusian\Tests\IOPools\Fixtures\SleepFor;
use Voyager\Contracts\IOPools\DeadWorkerException;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\ThreadPool;

function threadPool(EventLoop $loop, int $size = 2, ?int $max_jobs = null, float $sweep = 0.1): ThreadPool
{
    $root = dirname(__DIR__, 2);

    return new ThreadPool($loop, $root.'/vendor/autoload.php', $root, $size, $max_jobs, $sweep);
}

it('refuses to build without ext-parallel', function () {
    expect(fn () => threadPool(new EventLoop))->toThrow(EventLoopException::class, 'ext-parallel');
})->skip(extension_loaded('parallel'), 'ext-parallel is loaded');

describe('thread driver', function () {
    // Hooks may run before a skip is honoured, so they guard themselves too.
    beforeEach(function () {
        $this->loop = new EventLoop;
        $this->pool = extension_loaded('parallel') ? threadPool($this->loop) : null;
    });

    afterEach(function () {
        $this->pool?->shutDown();
    });

    it('refuses a hello timeout of zero', function () {
        $root = dirname(__DIR__, 2);

        expect(fn () => new ThreadPool($this->loop, $root.'/vendor/autoload.php', $root, 1, null, 0.1, 0.0))
            ->toThrow(EventLoopException::class, 'hello_timeout_s');
    });

    it('wakes on the bell, not on the sweep', function () {
        $loop = new EventLoop;
        $pool = threadPool($loop, 1, null, 5.0);           // a sweep this slow would fail the clock below
        $pool->warm(1);

        $start = microtime(true);
        $pool->submit(new SleepFor(0.05))->wait();

        expect(microtime(true) - $start)->toBeLessThan(0.5);

        $pool->shutDown();
    });

    it('rejects with DeadWorkerException when a gig calls exit(), within a sweep', function () {
        $this->pool->warm(1);

        $start = microtime(true);
        $promise = $this->pool->submit(new ExitingJob);

        expect(fn () => $promise->wait())->toThrow(DeadWorkerException::class, 'pool:0')
            ->and(microtime(true) - $start)->toBeLessThan(1.0);
    });

    it('replaces the runtime after an exit() and keeps serving', function () {
        try {
            $this->pool->submit(new ExitingJob)->wait();
        } catch (DeadWorkerException) {
        }

        expect($this->pool->workerCount())->toBe(0)
            ->and($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });

    it('lets run() end on its own: the sweep never outlives the work', function () {
        $sum = null;

        $this->pool->submit(new AddNumbers(1, 1))->then(function ($v) use (&$sum) {
            $sum = $v;
        });

        $start = microtime(true);
        $this->loop->run();                                 // no stop() anywhere

        expect($sum)->toBe(2)
            ->and(microtime(true) - $start)->toBeLessThan(2.0);
    });

    it('recycles a runtime after max_jobs without stalling the line', function () {
        $loop = new EventLoop;
        $pool = threadPool($loop, 1, 2);

        $answers = [];
        foreach (range(1, 5) as $n) {
            $answers[] = $pool->submit(new AddNumbers($n, 0))->wait();
        }

        expect($answers)->toBe([1, 2, 3, 4, 5]);

        $pool->shutDown();
    });

    it('removes its socket file on shutDown', function () {
        $this->pool->warm(1);
        $path = $this->pool->socketPath();

        expect(file_exists($path))->toBeTrue();

        $this->pool->shutDown();

        expect(file_exists($path))->toBeFalse();
    });

    it('can be used again after shutDown', function () {
        $this->pool->warm(1);
        $this->pool->shutDown();

        expect($this->pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);
    });
})->skip(! extension_loaded('parallel'), 'ext-parallel not loaded');
