<?php

use Voyager\Contracts\IOPools\Completion as CompletionContract;
use Voyager\Contracts\IOPools\Occurrence as OccurrenceContract;
use Voyager\Contracts\IOPools\QueuedIO;
use Voyager\IOPools\DTO\HttpResult;
use Voyager\IOPools\IOEventBag;
use Voyager\IOPools\IOPoolDock;
use Voyager\Vessel\Vessel as Container;

function dock(): IOPoolDock
{
    return new IOPoolDock(new Container, ['resources' => []]);
}

function happened(): OccurrenceContract
{
    return new class implements OccurrenceContract
    {
        public function ok(): bool
        {
            return true;
        }
    };
}

test('push appends and drain hands everything back in arrival order', function () {
    $dock = dock();

    $dock->push($first = happened());
    $dock->push($second = happened());

    expect($dock->drain()->all())->toBe([$first, $second]);
});

test('two pushes of the same mail shape are two entries — nothing coalesces', function () {
    $dock = dock();

    $dock->push($first = happened());
    $dock->push($second = happened());
    $dock->push($third = happened());

    expect($dock->drain()->count())->toBe(3);
});

test('drain empties the bag and hands back an IOEventBag', function () {
    $dock = dock();
    $dock->push(happened());

    $bag = $dock->drain();

    expect($bag)->toBeInstanceOf(IOEventBag::class)
        ->and($bag->count())->toBe(1)
        ->and($dock->drain()->isEmpty())->toBeTrue();
});

test('mail pushed after a drain waits for the next drain', function () {
    $dock = dock();
    $dock->push(happened());

    $bag = $dock->drain();
    $dock->push($late = happened());

    expect($bag->count())->toBe(1)
        ->and($dock->drain()->all())->toBe([$late]);
});

test('an empty resources config boots no drivers and pump is a quiet no-op', function () {
    $dock = dock();

    $dock->pump();

    expect($dock->http())->toBeNull()
        ->and($dock->async())->toBeNull()
        ->and($dock->drain()->isEmpty())->toBeTrue();
});

test('mail species carry their contracts for interface-keyed listeners', function () {
    $result = new HttpResult(name: 'x', ok: true, status: 200, headers: [], body: '');

    expect($result)->toBeInstanceOf(CompletionContract::class)
        ->and($result)->toBeInstanceOf(QueuedIO::class)
        ->and($result)->not->toBeInstanceOf(OccurrenceContract::class)
        ->and(happened())->toBeInstanceOf(QueuedIO::class);
});
