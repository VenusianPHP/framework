<?php

require_once __DIR__.'/Fixtures/tasks.php';

use Voyager\Concurrency\ProcessDriver;
use Voyager\Contracts\IOPools\Promise;
use Venusian\Tests\Concurrency\Fixtures\Tasks;
use Venusian\Tests\Concurrency\Fixtures\ConcurrencyApp;

beforeEach(function () {
    ConcurrencyApp::hasComputerApp() || $this->markTestSkipped('needs an app with a `computer` script: set VENUSIAN_APP_PATH');
    $this->app = ConcurrencyApp::bootComputerApp();
});
afterEach(fn () => isset($this->app) && ConcurrencyApp::tearDown($this->app, $this));

it('runs each task in its own `php computer` process and hands back the results by key', function () {
    $driver = $this->app->get('concurrency')->driver('process');

    $results = $driver->run(['sum' => Tasks\sum(20, 22), 'pid' => Tasks\pid()]);

    expect($driver)->toBeInstanceOf(ProcessDriver::class)
        ->and($results['sum'])->toBe(42)
        ->and($results['pid'])->not->toBe(getmypid());
});

it('rethrows a task\'s exception when it runs blocking', function () {
    expect(fn () => $this->app->get('concurrency')->driver('process')->run([
        'fine' => Tasks\returns('ok'),
        'declined' => Tasks\declines(),
    ]))->toThrow(DomainException::class, 'card declined');
});

it('runs the processes at the same time without blocking, and delivers their results as mail', function () {
    $started = hrtime(true);

    $promise = $this->app->get('concurrency')->driver('process')->async([
        'a' => Tasks\sleeps(600, 'first'),
        'b' => Tasks\sleeps(600, 'second'),
        'c' => Tasks\sleeps(600, 'third'),
    ], 'sleepers');

    expect($promise)->toBeInstanceOf(Promise::class)
        ->and($promise->settled())->toBeFalse();

    $mail = ConcurrencyApp::mail($this->app, 'sleepers');

    expect($mail->results)->toBe(['a' => 'first', 'b' => 'second', 'c' => 'third'])
        ->and($promise->wait())->toBe(['a' => 'first', 'b' => 'second', 'c' => 'third'])
        ->and(hrtime(true) - $started)->toBeLessThan(1_800_000_000);
});

it('delivers the failures beside the results, and rejects with the first in key order', function () {
    $promise = $this->app->get('concurrency')->driver('process')->async([
        'fine' => Tasks\returns('ok'),
        'declined' => Tasks\declines(),
        'lost' => Tasks\tooLong(),
    ], 'mixed');

    $mail = ConcurrencyApp::mail($this->app, 'mixed');

    expect($mail->results)->toBe(['fine' => 'ok'])
        ->and($mail->failures['declined'])->toBeInstanceOf(DomainException::class)
        ->and($mail->failures['lost'])->toBeInstanceOf(LengthException::class)
        ->and(fn () => $promise->wait())->toThrow(DomainException::class, 'card declined');
});

it('runs async() on the default driver, process here', function () {
    expect($this->app->get('concurrency')->async(['x' => Tasks\returns('by default')])->wait())
        ->toBe(['x' => 'by default']);
});
