<?php

use Venusian\Tests\TestCase;
use Voyager\IOPools\EventLoop;
use Voyager\Workflows\Runtimes\LoopRuntime;

// Process-pool workers boot this repo as an app. PackageManifest::write() needs
// bootstrap/cache; FileStream / PoolCrossing write under storage/app. Those dirs
// belong in the Venusian app skeleton, not this package — create them here so a
// clean clone's Pest run (CI or local) has them before any child is spawned.
foreach (['bootstrap/cache', 'storage/app'] as $relative) {
    $path = dirname(__DIR__).'/'.$relative;

    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature');

uses()->group('deferred')->in('Database/deferred');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

dataset('async runtimes', [
    'loop'     => [fn () => new LoopRuntime(new EventLoop)],
    'isolated' => [fn () => LoopRuntime::isolated()],
]);
dataset('overlapping async runtimes', [
    'loop' => [fn () => new LoopRuntime(new EventLoop)],
]);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
