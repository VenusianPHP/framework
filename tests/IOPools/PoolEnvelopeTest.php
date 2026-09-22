<?php

use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Venusian\Tests\IOPools\Fixtures\ExplodingJob;
use Venusian\Tests\IOPools\Fixtures\ReturnsClosure;
use Voyager\Contracts\IOPools\RemoteException;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\PoolEnvelope;

it('wraps a gig return as an ok envelope', function () {
    expect(PoolEnvelope::run(new AddNumbers(2, 3)))->toBe(['ok' => true, 'value' => 5]);
});

it('wraps a thrown gig as a failed envelope of strings', function () {
    $envelope = PoolEnvelope::run(new ExplodingJob('nope'));

    expect($envelope['ok'])->toBeFalse()
        ->and($envelope['class'])->toBe(RuntimeException::class)
        ->and($envelope['message'])->toBe('nope')
        ->and($envelope['trace'])->toContain('ExplodingJob');
});

it('fails the envelope when the answer cannot travel', function () {
    $envelope = PoolEnvelope::run(new ReturnsClosure);

    expect($envelope['ok'])->toBeFalse()
        ->and($envelope['message'])->toContain('Closure');
});

it('resolves a promise from an ok envelope', function () {
    $promise = (new EventLoop)->promise();

    PoolEnvelope::settle($promise, ['ok' => true, 'value' => 5]);

    expect($promise->wait())->toBe(5);
});

it('rejects a promise with RemoteException from a failed envelope', function () {
    $promise = (new EventLoop)->promise();

    PoolEnvelope::settle($promise, ['ok' => false, 'class' => 'LogicException', 'message' => 'bad', 'trace' => '#0 x']);

    expect(fn () => $promise->wait())->toThrow(function (RemoteException $e) {
        expect($e->remote_class)->toBe('LogicException')
            ->and($e->getMessage())->toBe('[LogicException] bad')
            ->and($e->remote_trace)->toBe('#0 x');
    });
});
