<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\Pivot;
use Mockery as m;

test('serializes pivots entities id', function () {
    $spy = m::spy(Pivot::class);

    $c = new Collection([$spy]);

    $c->getQueueableIds();

    $spy->shouldHaveReceived()
        ->getQueueableId()
        ->once();
});

test('serializes model entities by id', function () {
    $spy = m::spy(Model::class);

    $c = new Collection([$spy]);

    $c->getQueueableIds();

    $spy->shouldHaveReceived()
        ->getQueueableId()
        ->once();
});

/**
 * @throws \Exception
 */
test('json serialization of collection queueable ids works', function () {
    // When the ID of a Model is binary instead of int or string, the Collection
    // serialization + JSON encoding breaks because of UTF-8 issues. Encoding
    // of a QueueableCollection must favor QueueableEntity::queueableId().
    $mock = m::mock(Model::class, [
        'getKey' => random_bytes(10),
        'getQueueableId' => 'mocked',
    ]);

    $c = new Collection([$mock]);

    $payload = [
        'ids' => $c->getQueueableIds(),
    ];

    $this->assertNotFalse(
        json_encode($payload),
        'InstrumentCollection is not using the QueueableEntity::getQueueableId() method.'
    );
});
