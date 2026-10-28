<?php

namespace Venusian\Tests;


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

// The process driver's tests run `php computer` in this repo, which ships none: point it at the
// fixture beside ConcurrencyApp. COMPUTER_BINARY is a process-wide constant the first booted
// Kernel defaults to `computer`, so it is set here, before any app boots, and holds for the whole
// run; the fixture boots this same app. VENUSIAN_APP_PATH runs them in a real app's own instead.
getenv('VENUSIAN_APP_PATH') || define('COMPUTER_BINARY', __DIR__.'/Concurrency/Fixtures/computer');

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

// A pretended thread verdict and the fallback warning never outlive the test that set them.
uses()->afterEach(function () {
    \Voyager\IOPools\WorkerPools\Thread\ThreadSupport::flush();
    \Voyager\IOPools\WorkerPools\AutoPool::flush();
})->in(__DIR__);

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
