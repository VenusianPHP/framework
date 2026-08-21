<?php

use Mockery as m;
use Tests\Pagination\Fixtures\ConcretePaginator;
use Voyager\Database\Instrument\Collection;

test('collection loadMorphCount can chain on the paginator', function () {
    $relations = [
        'App\\User' => 'photos',
        'App\\Company' => ['employees', 'calendars'],
    ];

    $items = m::mock(Collection::class);
    $items->shouldReceive('loadMorphCount')->once()->with('parentable', $relations);

    $p = (new ConcretePaginator)->setCollection($items);

    expect($p->loadMorphCount('parentable', $relations))->toBe($p);
});
