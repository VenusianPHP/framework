<?php

use Voyager\Http\Async\HttpAsyncManager;
use Voyager\Http\Async\LoopCurlHandler;
use Voyager\Http\Client\HttpClientException;

require_once __DIR__.'/Support/helpers.php';

test('default driver is curl and memoised', function () {
    $m = new HttpAsyncManager(httpVessel());
    expect($m->handler())->toBeInstanceOf(LoopCurlHandler::class)
        ->and($m->driver())->toBe($m->driver());
});

test('no loop bound means no handler', function () {
    expect((new HttpAsyncManager(httpVessel(loop: false)))->handler())->toBeNull();
});

test('pcurl without the extension throws', function () {
    $m = new HttpAsyncManager(httpVessel(['async' => ['default' => 'pcurl']]));
    expect(fn () => $m->driver())->toThrow(HttpClientException::class);
})->skip(extension_loaded('pcurl'));
