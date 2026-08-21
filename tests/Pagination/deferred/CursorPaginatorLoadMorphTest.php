<?php

use Mockery as m;
use Tests\Pagination\Fixtures\ConcreteCursorPaginator;
use Voyager\Database\Instrument\Collection;

test('collection loadMorph can chain on the paginator', function () {
    $relations = [
        'App\\User' => 'photos',
        'App\\Company' => ['employees', 'calendars'],
    ];

    $items = m::mock(Collection::class);
    $items->shouldReceive('loadMorph')->once()->with('parentable', $relations);

    $p = (new ConcreteCursorPaginator)->setCollection($items);

    expect($p->loadMorph('parentable', $relations))->toBe($p);
});
