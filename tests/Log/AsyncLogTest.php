<?php

use Voyager\Log\AsyncWrites;
use Voyager\Log\WriteLog;
use Voyager\Core\RenderedInstance;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Log\Signals\MessageLogged;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Log\Fixtures\RecordingPool;

beforeEach(fn () => $this->app = LogApp::boot());
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('returns before the line is written, and a worker writes it', function () {
    $path = LogApp::channel($this->app, 'async');

    $promise = $this->app->get('log')->channel('async')->infoAsync('off the loop');

    expect($promise->settled())->toBeFalse()
        ->and(LogApp::lines($path))->toBe([]);

    expect($promise->wait())->toBeNull()
        ->and(LogApp::lines($path)[0])->toContain('.INFO: off the loop')
        ->and($this->app->get('process-pool')->workerCount())->toBeGreaterThan(0);
});

it('keeps the order the calls were made in, however many workers the pool has', function () {
    $path = LogApp::channel($this->app, 'async');
    $log = $this->app->get('log')->channel('async');

    $promises = array_map(fn (int $n) => $log->infoAsync("line {$n}"), range(1, 20));
    array_map(fn ($promise) => $promise->wait(), $promises);

    expect(array_map(fn (string $line): string => rtrim(substr($line, strpos($line, 'line '))), LogApp::lines($path)))
        ->toBe(array_map(fn (int $n): string => "line {$n}", range(1, 20)));
});

it('writes through the default channel from the LogManager', function () {
    $path = LogApp::channel($this->app, 'async');
    $this->app['config']->set('logging.default', 'async');

    $this->app->get('log')->warningAsync('from the manager')->wait();

    expect(LogApp::lines($path)[0])->toContain('.WARNING: from the manager');
});

it('writes a stack to every member', function () {
    $first = LogApp::channel($this->app, 'first');
    $second = LogApp::channel($this->app, 'second');
    $this->app['config']->set('logging.channels.both', ['driver' => 'stack', 'channels' => ['first', 'second']]);

    $this->app->get('log')->channel('both')->errorAsync('to both')->wait();

    expect(LogApp::lines($first)[0])->toContain('.ERROR: to both')
        ->and(LogApp::lines($second)[0])->toContain('.ERROR: to both');
});

it('carries an exception in the context across to the worker', function () {
    $path = LogApp::channel($this->app, 'async');
    $e = (function (Closure $unused): Throwable {
        return new DomainException('card declined', 402);
    })(fn () => null);

    $this->app->get('log')->channel('async')->errorAsync('Payment failed', ['exception' => $e])->wait();

    expect(LogApp::lines($path)[0])->toContain('(DomainException(code: 402): card declined at '.$e->getFile().':'.$e->getLine().')');
});

it('dispatches MessageLogged here, once, when the call is made', function () {
    LogApp::channel($this->app, 'async');
    $log = $this->app->get('log')->channel('async');
    $heard = [];
    $log->listen(function (MessageLogged $logged) use (&$heard) {
        $heard[] = "{$logged->level}: {$logged->message}";
    });

    $promise = $log->noticeAsync('heard here');

    expect($heard)->toBe(['notice: heard here']);

    $promise->wait();

    expect($heard)->toBe(['notice: heard here']);
});

it('sends nothing for a level the channel ignores', function () {
    $path = LogApp::channel($this->app, 'async', ['level' => 'error']);

    expect($this->app->get('log')->channel('async')->infoAsync('below the level')->wait())->toBeNull()
        ->and(LogApp::lines($path))->toBe([])
        ->and($this->app->get('process-pool')->workerCount())->toBe(0);
});

it('prefers the thread pool when it is on', function () {
    LogApp::channel($this->app, 'async');
    $threads = new RecordingPool($this->app->get(Loop::class));
    $this->app->registerInstance('thread-pool', $threads);

    $this->app->get('log')->channel('async')->infoAsync('to the threads')->wait();

    expect($threads->gigs)->toHaveCount(1)
        ->and($threads->gigs[0])->toBeInstanceOf(WriteLog::class)
        ->and($this->app->get('process-pool')->workerCount())->toBe(0);
});

it('refuses an async call when no pool is on', function () {
    // An unbooted app binds no pools. Building it makes it the global container: the booted one goes back.
    $writes = new AsyncWrites(new RenderedInstance(dirname(__DIR__, 2)));
    ControlPanel::setInstance($this->app);

    expect(fn () => $writes->submit('async', [], 'info', 'nowhere to go', []))
        ->toThrow(InvalidArgumentException::class, 'Async logging writes through a worker pool, and none is on: enable io-pools.pool_workers.threads or io-pools.pool_workers.process.');
});

it('writes the calls still queued when the loop stops, blocking; the pool settles the gig already out', function () {
    $path = LogApp::channel($this->app, 'async');
    $log = $this->app->get('log')->channel('async');
    $loop = $this->app->get(Loop::class);

    [$out, $second, $third] = [$log->infoAsync('first'), $log->infoAsync('second'), $log->infoAsync('third')];

    $loop->at(0, fn () => $loop->stop());
    $loop->run();

    expect($second->fulfilled())->toBeTrue()
        ->and($third->fulfilled())->toBeTrue()
        ->and($out->rejected())->toBeTrue()
        ->and(array_map(fn (string $line): string => rtrim(substr($line, strpos($line, 'INFO: ') + 6)), LogApp::lines($path)))
        ->toBe(['second', 'third']);
});

it('writes an async call from a script that exits without running the loop or waiting', function () {
    $path = sys_get_temp_dir().'/venusian-log-'.bin2hex(random_bytes(6)).'.log';

    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/Fixtures/exit-without-waiting.php').' '.escapeshellarg($path), $output, $status);

    expect($status)->toBe(0)
        ->and(LogApp::lines($path)[0] ?? '')->toContain('.INFO: written after the script ended');

    unlink($path);
});

it('rejects only the call that can\'t cross, and writes the ones around it', function () {
    $path = LogApp::channel($this->app, 'async');
    $log = $this->app->get('log')->channel('async');

    [$before, $bad, $after] = [
        $log->infoAsync('before'),
        $log->infoAsync('holds a closure', ['callback' => fn () => null]),
        $log->infoAsync('after'),
    ];

    expect(fn () => $bad->wait())->toThrow(InvalidArgumentException::class, "This log call can't cross to a worker: Serialization of 'Closure' is not allowed");

    $after->wait();

    expect($before->fulfilled())->toBeTrue()
        ->and(array_map(fn (string $line): string => rtrim(substr($line, strpos($line, 'INFO: ') + 6)), LogApp::lines($path)))
        ->toBe(['before', 'after']);
});

it('rejects a call on a channel whose config holds a closure', function () {
    $this->app['config']->set('logging.channels.made', ['driver' => 'custom', 'via' => fn (array $config) => new Monolog\Logger('made', [new Monolog\Handler\TestHandler()])]);

    expect(fn () => $this->app->get('log')->channel('made')->infoAsync('built by a closure')->wait())
        ->toThrow(InvalidArgumentException::class, "This log call can't cross to a worker: Serialization of 'Closure' is not allowed");
});

it('rejects a call whose Context holds a value that can\'t cross', function () {
    LogApp::channel($this->app, 'async');
    $this->app->get(Voyager\Log\Context\Repository::class)->add('callback', fn () => null);

    expect(fn () => $this->app->get('log')->channel('async')->infoAsync('in a closure context')->wait())
        ->toThrow(InvalidArgumentException::class, "This log call can't cross to a worker: Serialization of 'Closure' is not allowed");
});

it('rejects with the worker\'s error when the write fails there, and keeps writing after', function () {
    $good = LogApp::channel($this->app, 'async');
    LogApp::channel($this->app, 'broken', ['path' => '/dev/null/not-a-directory/venusian.log']);
    $log = $this->app->get('log');

    expect(fn () => $log->channel('broken')->errorAsync('nowhere')->wait())
        ->toThrow(Voyager\Contracts\IOPools\WorkerPools\RemoteException::class, 'UnexpectedValueException');

    $log->channel('async')->infoAsync('still writing')->wait();

    expect(LogApp::lines($good)[0])->toContain('.INFO: still writing');
});

it('writes through a fresh worker after an earlier run of the loop shut the pool down', function () {
    $path = LogApp::channel($this->app, 'async');
    $log = $this->app->get('log')->channel('async');
    $loop = $this->app->get(Loop::class);

    $log->infoAsync('first run')->wait();
    $loop->at(0, fn () => $loop->stop());
    $loop->run();

    $log->infoAsync('second run')->wait();

    expect(array_map(fn (string $line): string => rtrim(substr($line, strpos($line, 'INFO: ') + 6)), LogApp::lines($path)))
        ->toBe(['first run', 'second run']);
});
