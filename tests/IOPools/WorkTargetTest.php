<?php

use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Venusian\Tests\IOPools\Fixtures\ExplodingJob;
use Venusian\Tests\IOPools\Fixtures\SleepFor;
use Voyager\Concurrency\SyncDriver;
use Voyager\Config\Repository;
use Voyager\Contracts\Concurrency\Driver as ConcurrencyDriver;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\RemoteException;
use Voyager\Contracts\IOPools\WorkerPool;
use Voyager\Contracts\IOPools\WorkTarget;
use Voyager\Contracts\Queue\Factory as QueueFactory;
use Voyager\Contracts\Queue\Queue;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\ProcessPool;
use Voyager\IOPools\WorkTargetManager;
use Voyager\IOPools\WorkTargets\ConcurrencyTarget;
use Voyager\IOPools\WorkTargets\DeferTarget;
use Voyager\IOPools\WorkTargets\PoolTarget;
use Voyager\IOPools\WorkTargets\QueueTarget;
use Voyager\IOPools\WorkTargets\SyncTarget;
use Voyager\Vessel\ControlPanel;
use Mockery as m;

function workTargets(EventLoop $loop, array $config = []): WorkTargetManager
{
    $root = dirname(__DIR__, 2);
    $vessel = new ControlPanel;
    $vessel->registerInstance('config', new Repository(['io-pools' => ['work' => $config]]));
    $vessel->registerInstance(Loop::class, $loop);
    $vessel->registerInstance(WorkerPool::class, new ProcessPool($loop, [PHP_BINARY, $root.'/src/Voyager/IOPools/bin/pool-worker', $root.'/vendor/autoload.php', $root], 1));
    $vessel->registerInstance(ConcurrencyDriver::class, new SyncDriver);
    $vessel->registerInstance(QueueFactory::class, $queues = m::mock(QueueFactory::class));
    $queues->shouldReceive('connection')->andReturn($queue = m::mock(Queue::class));
    $queue->shouldReceive('push')->andReturnUsing(fn ($job) => 'job:'.spl_object_id($job));   // a queue answers with an id, not a result

    return new WorkTargetManager($vessel);
}

afterEach(fn () => m::close());

dataset('work targets', [
    'sync'        => ['sync', SyncTarget::class],
    'defer'       => ['defer', DeferTarget::class],
    'pool'        => ['pool', PoolTarget::class],
    'concurrency' => ['concurrency', ConcurrencyTarget::class],
]);

it('runs a gig and resolves with its return', function (string $name, string $class) {
    $loop = new EventLoop;
    $target = workTargets($loop)->driver($name);

    expect($target)->toBeInstanceOf($class)
        ->and($target->run(new AddNumbers(2, 3)))->toBeInstanceOf(Promise::class)
        ->and($target->run(new AddNumbers(2, 3))->wait())->toBe(5);
})->with('work targets');

it('rejects with what the gig threw', function (string $name) {
    $target = workTargets(new EventLoop)->driver($name);

    expect(fn () => $target->run(new ExplodingJob('nope'))->wait())
        ->toThrow($name === 'pool' ? RemoteException::class : RuntimeException::class, 'nope');
})->with('work targets');

/** Flips a static so the test can see whether handle() ran yet. */
final class FlagGig implements \Voyager\Contracts\IOPools\ShouldPool
{
    public static bool $ran = false;
    public function handle(): mixed { return self::$ran = true; }
}

it('defer runs on the next turn, not now', function () {
    $loop = new EventLoop;
    FlagGig::$ran = false;

    $promise = workTargets($loop)->driver('defer')->run(new FlagGig);

    expect(FlagGig::$ran)->toBeFalse()->and($promise->wait())->toBeTrue()->and(FlagGig::$ran)->toBeTrue();
});

it('pool does not block the loop while the gig sleeps', function () {
    $loop = new EventLoop;
    $beats = 0;
    $ticker = $loop->every(0.02, function () use (&$beats, &$ticker) { if (++$beats === 3) $ticker->cancel(); }, 'beats');

    $answer = workTargets($loop)->driver('pool')->run(new SleepFor(0.1, 'done'));
    $loop->run();

    expect($answer->wait())->toBe('done')->and($beats)->toBe(3);
});

it('defaults to the configured target, else pool', function () {
    expect(workTargets(new EventLoop)->getDefaultDriver())->toBe('pool')
        ->and(workTargets(new EventLoop, ['default' => 'defer'])->driver())->toBeInstanceOf(DeferTarget::class);
});

it('queue pushes the gig and resolves with the job id, not the result', function () {
    $target = workTargets(new EventLoop)->driver('queue');

    expect($target)->toBeInstanceOf(QueueTarget::class)
        ->and($target->run(new AddNumbers(2, 3))->wait())->toStartWith('job:');
});
