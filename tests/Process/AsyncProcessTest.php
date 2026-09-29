<?php

use Voyager\Process\Pipe;
use Voyager\Process\Pool;
use Voyager\Process\Factory;
use Voyager\Process\ProcessResult;
use Voyager\Process\ProcessPoolResults;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\Process\Exceptions\ProcessTimedOutException;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Process\Fixtures\CountingBackend;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->process = new Factory();
    $this->loop = $this->app->get(Loop::class);
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** The app's loop swapped for one on $backend, counting its waits. */
function countedLoop(object $test, string $backend): CountingBackend
{
    $counting = new CountingBackend(match ($backend) {
        'kqueue' => new KqueueWaiterBackend(),
        'epoll' => new EpollWaiterBackend(),
        'select' => new StreamSelectWaiterBackend(),
    });
    $registry = new ResourceRegistry();
    $test->loop = new EventLoop($registry, new LoopWaiter($registry, $counting, 16_000_000), new GuzzlePromiseEngine());
    $test->app->registerInstance('event-loop', $test->loop);

    return $counting;
}

dataset('exit wakes', function () {
    if (extension_loaded('kqueue')) {
        yield 'kqueue: the process filter' => ['kqueue'];
    }

    if (extension_loaded('epoll')) {
        yield 'epoll: SIGCHLD' => ['epoll'];
    }

    yield 'select: SIGCHLD' => ['select'];
});

it('hands back a promise at once and settles it with the process\'s result', function () {
    $started = hrtime(true);
    $promise = $this->process->start(['sh', '-c', 'sleep 0.2; echo done; exit 3'])->waitAsync();

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and(hrtime(true) - $started)->toBeLessThan(100_000_000)
        ->and($promise->settled())->toBeFalse();

    $result = $promise->wait();

    expect($result)->toBeInstanceOf(ProcessResult::class)
        ->and($result->output())->toBe("done\n")
        ->and($result->exitCode())->toBe(3)
        ->and($result->failed())->toBeTrue();
});

it('checks the timeout once, when it runs out, not on a timer', function (string $backend) {
    $counting = countedLoop($this, $backend);
    $invoked = $this->process->timeout(1)->start(['sleep', '5']);

    expect(fn () => $invoked->waitAsync()->wait())->toThrow(ProcessTimedOutException::class)
        ->and($counting->waits)->toBeLessThan(6);
})->with('exit wakes')->skip(! extension_loaded('pcntl'), 'SIGCHLD needs pcntl');

it('keeps the loop turning while it waits', function () {
    $ticks = 0;
    $this->loop->every(0.05, function () use (&$ticks) { $ticks++; }, 'ticker');

    $this->process->runAsync(['sleep', '0.3'])->wait();
    $this->loop->forget('ticker');

    expect($ticks)->toBeGreaterThanOrEqual(4);
});

it('wakes the loop on the exit, without checking on a timer', function (string $backend) {
    $counting = countedLoop($this, $backend);
    $started = hrtime(true);

    $result = $this->process->runAsync(['sleep', '0.3'])->wait();

    expect($result->successful())->toBeTrue()
        ->and(hrtime(true) - $started)->toBeLessThan(400_000_000)
        ->and($counting->waits)->toBeLessThan(6);
})->with('exit wakes')->skip(! extension_loaded('pcntl'), 'SIGCHLD needs pcntl');

it('settles a process that had already exited when the wait began', function (string $backend) {
    countedLoop($this, $backend);
    $invoked = $this->process->start(['true']);
    usleep(100_000);

    expect($invoked->waitAsync()->wait()->successful())->toBeTrue();
})->with('exit wakes');

it('hands the output to the callback as it arrives', function () {
    $heard = [];
    // The child prints only once the gate exists: output read before waitAsync() attaches its
    // callback (start() reads the pipes once) isn't handed to it.
    $gate = sys_get_temp_dir().'/venusian-gate-'.bin2hex(random_bytes(6));
    $invoked = $this->process->start(['sh', '-c', 'while [ ! -e "$0" ]; do sleep 0.01; done; printf first; sleep 0.3; printf second', $gate]);
    $promise = $invoked->waitAsync(function (string $type, string $buffer) use (&$heard) {
        $heard[] = [$buffer, hrtime(true)];
    });
    touch($gate);

    try {
        $result = $promise->wait();
    } finally {
        unlink($gate);
    }

    $settled_at = hrtime(true);

    expect(implode('', array_column($heard, 0)))->toBe('firstsecond')
        ->and($heard[0][0])->toBe('first')
        ->and($settled_at - $heard[0][1])->toBeGreaterThan(200_000_000)
        ->and($result->output())->toBe('firstsecond');
});

it('still hands the output to start()\'s callback', function () {
    $heard = '';
    $invoked = $this->process->start(['sh', '-c', 'printf one; sleep 0.1; printf two'], function (string $type, string $buffer) use (&$heard) {
        $heard .= $buffer;
    });

    $invoked->waitAsync()->wait();

    expect($heard)->toBe('onetwo');
});

it('rejects with ProcessTimedOutException and stops a process past its timeout', function () {
    $started = hrtime(true);
    $invoked = $this->process->timeout(1)->start(['sleep', '5']);

    expect(fn () => $invoked->waitAsync()->wait())->toThrow(ProcessTimedOutException::class)
        ->and(hrtime(true) - $started)->toBeLessThan(1_500_000_000)
        ->and($invoked->running())->toBeFalse();
});

it('refuses an output callback when output is disabled', function () {
    $invoked = $this->process->quietly()->start(['true']);

    expect(fn () => $invoked->waitAsync(fn () => null)->wait())
        ->toThrow(LogicException::class, 'Output has been disabled, enable it to allow the use of a callback.');
});

it('hands every waitAsync() on one process the same promise', function () {
    $invoked = $this->process->start(['true']);

    expect($invoked->waitAsync())->toBe($invoked->waitAsync());
});

it('runs a pool through concurrentlyAsync()', function () {
    $results = $this->process->concurrentlyAsync(function (Pool $pool) {
        $pool->as('a')->command(['echo', 'a']);
        $pool->as('b')->command(['echo', 'b']);
    })->wait();

    expect(trim($results['a']->output()))->toBe('a')
        ->and(trim($results['b']->output()))->toBe('b');
});

it('waits on a pool without blocking, keyed as the pool was', function () {
    $started = hrtime(true);

    $results = $this->process->pool(function (Pool $pool) {
        $pool->as('first')->command(['sh', '-c', 'sleep 0.3; echo 1']);
        $pool->as('second')->command(['sh', '-c', 'sleep 0.3; echo 2']);
        $pool->as('third')->command(['sh', '-c', 'sleep 0.3; echo 3']);
    })->runAsync()->wait();

    expect($results)->toBeInstanceOf(ProcessPoolResults::class)
        ->and(array_map(fn ($result) => trim($result->output()), $results->collect()->all()))->toBe(['first' => '1', 'second' => '2', 'third' => '3'])
        ->and(hrtime(true) - $started)->toBeLessThan(700_000_000);
});

it('runs a pipe without blocking, each stage fed the one before', function () {
    $result = $this->process->pipeAsync(function (Pipe $pipe) {
        $pipe->command(['printf', "banana\napple\ncherry\n"]);
        $pipe->command(['sort']);
        $pipe->command(['head', '-n', '2']);
    })->wait();

    expect($result->output())->toBe("apple\nbanana\n");
});

it('ends a pipe at the first stage that fails', function () {
    $result = $this->process->pipeAsync(function (Pipe $pipe) {
        $pipe->command(['sh', '-c', 'echo partial; exit 2']);
        $pipe->command(['sh', '-c', 'echo never']);
    })->wait();

    expect($result->exitCode())->toBe(2)
        ->and($result->output())->toBe("partial\n");
});

it('settles a faked process with its fake result', function () {
    $this->process->fake(['ls *' => $this->process->result('fake listing')]);

    // A fake's output ends in a newline, as a real process's line would.
    expect($this->process->runAsync('ls -la')->wait()->output())->toBe("fake listing\n");
    $this->process->assertRan('ls -la');
});
