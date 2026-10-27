<?php

use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\IOPools\IOPoolsServiceProvider;
use Voyager\IOPools\WorkerPools\AutoPool;
use Voyager\IOPools\WorkerPools\Thread\ThreadSupport;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->warnings = [];
    $this->app->registerInstance('log', new class($this) {
        public function __construct(private object $test) {}

        public function warning(string $message): void
        {
            $this->test->warnings[] = $message;
        }
    });
    ThreadSupport::flush();
    AutoPool::flush();
});
afterEach(function () {
    ThreadSupport::flush();
    AutoPool::flush();
    LogApp::tearDown($this->app, $this);
});

/** A pool stand-in nothing here runs: tearDown only shuts it down. */
function idlePool(): object
{
    return new class {
        public function shutDown(): void {}
    };
}

/** Both pools bound, as stand-ins. */
function bothPools($app): void
{
    $app->registerInstance('thread-workers', idlePool());
    $app->registerInstance('process-workers', idlePool());
}

it('falls back to the process workers when threads do not run, warning once with why', function () {
    bothPools($this->app);
    ThreadSupport::pretend(false, 'a parallel\Runtime in a child exited 139');

    expect(AutoPool::binding($this->app))->toBe('process-workers')
        ->and(AutoPool::binding($this->app))->toBe('process-workers')
        ->and($this->warnings)->toHaveCount(1)
        ->and($this->warnings[0])->toContain('exited 139')->toContain('process workers');
});

it('picks the thread workers when threads run', function () {
    bothPools($this->app);
    ThreadSupport::pretend(true, 'a parallel\Runtime ran in a child');

    expect(AutoPool::binding($this->app))->toBe('thread-workers')
        ->and($this->warnings)->toBe([]);
});

it('names no pool when none is on, or only a thread pool that cannot run', function () {
    ThreadSupport::pretend(false, 'ext-parallel is not loaded');
    $empty = new Voyager\Vessel\ControlPanel;

    expect(AutoPool::binding($empty))->toBeNull();

    $empty->registerInstance('thread-workers', idlePool());

    expect(AutoPool::binding($empty))->toBeNull();
});

it('never probes for work that offloads nothing', function () {
    $probed = new ReflectionProperty(ThreadSupport::class, 'probed');
    $earlier = $probed->getValue();
    $probed->setValue(null, null);
    bothPools($this->app);

    try {
        $this->app->get('process-workers');
        $after = $probed->getValue();
    } finally {
        $probed->setValue(null, $earlier);
    }

    expect($after)->toBeNull();
});

it('fails a thread pool asked for by name with why threads do not run, instead of switching', function () {
    $this->app['config']->set('io-pools.pool_workers.threads.enabled', true);
    (new IOPoolsServiceProvider($this->app))->boot();
    ThreadSupport::pretend(false, 'a parallel\Runtime in a child did not answer within 10s');

    expect(fn () => $this->app->get('thread-workers'))
        ->toThrow(IOPoolsException::class, "The thread workers can't run in this PHP: a parallel\\Runtime in a child did not answer within 10s.");
})->skip(! PHP_ZTS || ! extension_loaded('parallel'), 'registering the thread workers needs a ZTS build with ext-parallel');
