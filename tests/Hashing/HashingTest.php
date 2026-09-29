<?php

use Voyager\Hashing\HashGig;
use Voyager\Hashing\HashManager;
use Voyager\Hashing\ArgonHasher;
use Voyager\Hashing\BcryptHasher;
use Voyager\Hashing\CheckHashGig;
use Voyager\Hashing\Argon2IdHasher;
use Voyager\Contracts\Hashing\Hasher;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Venusian\Tests\Log\Fixtures\LogApp;
use Venusian\Tests\Log\Fixtures\RecordingPool;

beforeEach(function () {
    $this->app = LogApp::boot();
    // Low costs keep the suite quick; the async tests check the worker hashes under them too.
    $this->app['config']->set('hashing.bcrypt.rounds', 4);
    $this->app['config']->set('hashing.argon', ['memory' => 1024, 'threads' => 1, 'time' => 1, 'verify' => true]);
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('binds the manager, and the default driver as the Hasher', function () {
    expect($this->app->make('hash'))->toBeInstanceOf(HashManager::class)
        ->and($this->app->make(HashManager::class))->toBe($this->app->make('hash'))
        ->and($this->app->make(Hasher::class))->toBeInstanceOf(BcryptHasher::class)
        ->and($this->app['config']->get('hashing.driver'))->toBe('bcrypt');
});

dataset('drivers', [
    'bcrypt' => ['bcrypt', BcryptHasher::class, 'bcrypt'],
    'argon' => ['argon', ArgonHasher::class, 'argon2i'],
    'argon2id' => ['argon2id', Argon2IdHasher::class, 'argon2id'],
]);

it('makes, checks and tells a hash\'s algorithm apart', function (string $driver, string $class, string $algorithm) {
    $hasher = $this->app->make('hash')->driver($driver);
    $hash = $hasher->make('correct horse');

    expect($hasher)->toBeInstanceOf($class)
        ->and($hash)->not->toBe('correct horse')
        ->and($hasher->check('correct horse', $hash))->toBeTrue()
        ->and($hasher->check('wrong horse', $hash))->toBeFalse()
        ->and($hasher->check('correct horse', null))->toBeFalse()
        ->and($hasher->info($hash)['algoName'])->toBe($algorithm)
        ->and($hasher->needsRehash($hash))->toBeFalse();
})->with('drivers');

it('uses the configured bcrypt rounds, and wants a rehash once they change', function () {
    $hash = $this->app->make('hash')->make('secret');

    expect($this->app->make('hash')->info($hash)['options']['cost'])->toBe(4)
        ->and($this->app->make('hash')->needsRehash($hash, ['rounds' => 5]))->toBeTrue();
});

it('refuses to check a hash made by another algorithm when verify is on', function () {
    $argon = $this->app->make('hash')->driver('argon')->make('secret');
    $bcrypt = new BcryptHasher(['rounds' => 4, 'verify' => true]);

    expect(fn () => $bcrypt->check('secret', $argon))->toThrow(RuntimeException::class, 'This password does not use the Bcrypt algorithm.');
});

it('makes a hash in a worker, under this process\'s config', function () {
    $promise = $this->app->make('hash')->makeAsync('correct horse');

    expect($promise)->toBeInstanceOf(Promise::class);

    $hash = $promise->wait();

    expect($this->app->make('hash')->check('correct horse', $hash))->toBeTrue()
        ->and($this->app->make('hash')->info($hash)['options']['cost'])->toBe(4)
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('makes a hash in a worker with the driver and options it is given', function () {
    $hash = $this->app->make('hash')->makeAsync('correct horse', ['rounds' => 5], 'bcrypt')->wait();
    $argon = $this->app->make('hash')->makeAsync('correct horse', [], 'argon2id')->wait();

    expect($this->app->make('hash')->info($hash)['options']['cost'])->toBe(5)
        ->and($this->app->make('hash')->driver('argon2id')->info($argon)['algoName'])->toBe('argon2id');
});

it('checks a hash in a worker', function () {
    $hash = $this->app->make('hash')->make('correct horse');

    expect($this->app->make('hash')->checkAsync('correct horse', $hash)->wait())->toBeTrue()
        ->and($this->app->make('hash')->checkAsync('wrong horse', $hash)->wait())->toBeFalse()
        ->and($this->app->make('hash')->checkAsync('correct horse', null)->wait())->toBeFalse();
});

it('hashes on the thread pool when it is on', function () {
    $threads = new RecordingPool($this->app->get(Loop::class));
    $this->app->registerInstance('thread-workers', $threads);

    $hash = $this->app->make('hash')->makeAsync('correct horse')->wait();
    $this->app->make('hash')->checkAsync('correct horse', $hash)->wait();

    expect(array_map(fn ($gig) => $gig::class, $threads->gigs))->toBe([HashGig::class, CheckHashGig::class])
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);
});

it('runs a HashGig handed to a pool directly, on the worker\'s own config', function () {
    $hash = $this->app->get('process-workers')->submit(new HashGig('correct horse'))->wait();

    expect($this->app->make('hash')->check('correct horse', $hash))->toBeTrue()
        ->and($this->app->make('hash')->info($hash)['options']['cost'])->toBe(12);
});

it('refuses async hashing when no pool is on', function () {
    // A container with config and no worker pools bound.
    $vessel = new Voyager\Vessel\ControlPanel();
    $vessel->registerInstance('config', new Voyager\Config\Repository(['hashing' => ['driver' => 'bcrypt']]));
    $manager = new HashManager($vessel);
    Voyager\Vessel\ControlPanel::setInstance($this->app);

    expect(fn () => $manager->makeAsync('secret'))
        ->toThrow(InvalidArgumentException::class, 'Async hashing runs on a worker pool, and none is on: enable io-pools.pool_workers.threads or io-pools.pool_workers.process.');
});
