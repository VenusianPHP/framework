<?php

use Voyager\Pagination\Paginator;

test('simple paginator returns relevant context information', function () {
    /** @var Paginator<int, string> $p */
    $p = new Paginator(['item3', 'item4', 'item5'], 2, 2);

    expect($p->currentPage())->toEqual(2);
    expect($p->hasPages())->toBeTrue();
    expect($p->hasMorePages())->toBeTrue();
    expect($p->items())->toEqual(['item3', 'item4']);

    $pageInfo = [
        'per_page' => 2,
        'current_page' => 2,
        'first_page_url' => '/?page=1',
        'current_page_url' => '/?page=2',
        'next_page_url' => '/?page=3',
        'prev_page_url' => '/?page=1',
        'from' => 3,
        'to' => 4,
        'data' => ['item3', 'item4'],
        'path' => '/',
    ];

    expect($p->toArray())->toEqual($pageInfo);
});

test('paginator removes trailing slashes', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 2, 2, ['path' => 'http://website.com/test/']);

    expect($p->previousPageUrl())->toBe('http://website.com/test?page=1');
});

test('paginator generates urls without trailing slash', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 2, 2, ['path' => 'http://website.com/test']);

    expect($p->previousPageUrl())->toBe('http://website.com/test?page=1');
});

test('it retrieves the paginator options', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 2, 2, ['path' => 'http://website.com/test']);

    expect($p->getOptions())->toBe(['path' => 'http://website.com/test']);
});

test('paginator returns path', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 2, 2, ['path' => 'http://website.com/test']);

    expect($p->path())->toBe('http://website.com/test');
});

test('it can transform paginator items', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 3, 1, ['path' => 'http://website.com/test']);

    $p->through(function ($item) {
        return substr($item, 4, 1);
    });

    expect($p)->toBeInstanceOf(Paginator::class);
    expect($p->items())->toBe(['1', '2', '3']);
});

test('paginator toJson', function () {
    $p = new Paginator(['item1', 'item2', 'item3'], 3, 1);
    $results = $p->toJson();
    $expected = json_encode($p->toArray());

    expect(json_decode($results, true))->toEqual(json_decode($expected, true));
    expect($results)->toBe($expected);
});

test('paginator toPrettyJson', function () {
    $p = new Paginator(['item/1', 'item/2', 'item/3'], 3, 1);
    $results = $p->toPrettyJson();
    $expected = $p->toJson(JSON_PRETTY_PRINT);

    expect(json_decode($results, true))->toEqual(json_decode($expected, true));
    expect($results)->toBe($expected);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');
    expect($results)->toContain('item\/1');

    $results = $p->toPrettyJson(JSON_UNESCAPED_SLASHES);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');
    expect($results)->toContain('item/1');
});
