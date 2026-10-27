<?php

use Voyager\IOPools\WorkerPools\Thread\ThreadSupport;

it('says why a PHP without ZTS or ext-parallel cannot run threads', function () {
    $probe = ThreadSupport::probe();

    expect($probe->supported)->toBeFalse()
        ->and($probe->reason)->toBe(PHP_ZTS ? 'ext-parallel is not loaded' : 'this PHP is not a ZTS build');
})->skip(PHP_ZTS && extension_loaded('parallel'), 'this PHP has ZTS and ext-parallel');

it('runs a parallel\Runtime in a child before saying threads work, and answers once per process', function () {
    $probe = ThreadSupport::probe();

    expect($probe->reason)->toBeString()->not->toBeEmpty()
        ->and(ThreadSupport::probe())->toBe($probe);

    if ($probe->supported) {
        expect((new parallel\Runtime)->run(fn () => 6 * 7)->value())->toBe(42);
    } else {
        expect($probe->reason)->toMatch('/exited \d+|did not answer|could not start/');
    }
})->skip(! PHP_ZTS || ! extension_loaded('parallel'), 'needs a ZTS build with ext-parallel to probe');
