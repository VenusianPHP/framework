<?php

use Voyager\Concurrency\ConcurrencyManager;
use Voyager\Concurrency\ProcessDriver;
use Voyager\Concurrency\SyncDriver;
use Voyager\Config\Repository;
use Voyager\Process\Factory as ProcessFactory;
use Voyager\Vessel\Vessel;

/**
 * Build the smallest container the manager needs.
 *
 * The manager only ever asks the application for its configuration, for a
 * process factory, and whether it is running in the console, so a plain
 * Vessel carrying those three answers stands in for a booted application.
 */
function concurrencyManagerApp(array $config = [], bool $runningInConsole = true)
{
    $vessel = new class($runningInConsole) extends Vessel
    {
        public function __construct(protected bool $console)
        {
            //
        }

        public function runningInConsole()
        {
            return $this->console;
        }
    };

    $vessel->instance('config', new Repository($config));
    $vessel->instance(ProcessFactory::class, new ProcessFactory);

    Vessel::setInstance($vessel);

    return $vessel;
}

beforeEach(function () {
    $this->previousVessel = Vessel::getInstance();
});

afterEach(function () {
    Vessel::setInstance($this->previousVessel);
});

test('it resolves the sync driver', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    expect($manager->driver('sync'))->toBeInstanceOf(SyncDriver::class);
});

test('it resolves the process driver', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    expect($manager->driver('process'))->toBeInstanceOf(ProcessDriver::class);
});

test('it defaults to the process driver', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    expect($manager->getDefaultInstance())->toBe('process')
        ->and($manager->driver())->toBeInstanceOf(ProcessDriver::class);
});

test('the default instance is read from configuration', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp(['concurrency.default' => 'sync']));

    expect($manager->getDefaultInstance())->toBe('sync')
        ->and($manager->driver())->toBeInstanceOf(SyncDriver::class);
});

test('the legacy driver configuration key is honored', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp(['concurrency.driver' => 'sync']));

    expect($manager->getDefaultInstance())->toBe('sync');
});

test('the default instance can be set', function () {
    $app = concurrencyManagerApp();
    $manager = new ConcurrencyManager($app);

    $manager->setDefaultInstance('sync');

    expect($manager->getDefaultInstance())->toBe('sync')
        ->and($app['config']['concurrency.default'])->toBe('sync')
        ->and($app['config']['concurrency.driver'])->toBe('sync');
});

test('instances are resolved once', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    expect($manager->driver('sync'))->toBe($manager->driver('sync'));
});

test('unknown drivers are rejected', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    $manager->driver('swoole');
})->throws(InvalidArgumentException::class, 'Instance driver [swoole] is not supported.');

test('the fork driver may not be used outside the console', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp(runningInConsole: false));

    $manager->driver('fork');
})->throws(RuntimeException::class, 'Due to PHP limitations, the fork driver may not be used within web requests.');

test('the fork driver requires the spatie fork package', function () {
    $manager = new ConcurrencyManager(concurrencyManagerApp());

    $manager->driver('fork');
})->throws(RuntimeException::class, 'Please install the "spatie/fork" Composer package in order to utilize the "fork" driver.')
    ->skip(fn () => class_exists(\Spatie\Fork\Fork::class), 'The spatie/fork package is installed.');
