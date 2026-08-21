<?php

use Voyager\NutsAndBolts\Collection;
use Voyager\Pagination\Cursor;
use Voyager\Pagination\CursorPaginator;

test('it returns relevant context information', function () {
    $p = new CursorPaginator($array = [['id' => 1], ['id' => 2], ['id' => 3]], 2, null, [
        'parameters' => ['id'],
    ]);

    expect($p->hasPages())->toBeTrue();
    expect($p->hasMorePages())->toBeTrue();
    expect($p->items())->toEqual([['id' => 1], ['id' => 2]]);

    $nextCursor = (new Cursor(['id' => 2], true))->encode();

    $pageInfo = [
        'data' => [['id' => 1], ['id' => 2]],
        'path' => '/',
        'per_page' => 2,
        'next_cursor' => $nextCursor,
        'next_page_url' => '/?cursor='.$nextCursor,
        'prev_cursor' => null,
        'prev_page_url' => null,
    ];

    expect($p->toArray())->toEqual($pageInfo);
});

test('paginator removes trailing slashes', function () {
    $p = new CursorPaginator($array = [['id' => 4], ['id' => 5], ['id' => 6]], 2, null,
        ['path' => 'http://website.com/test/', 'parameters' => ['id']]);

    $nextCursor = (new Cursor(['id' => 5], true))->encode();

    expect($p->nextPageUrl())->toBe('http://website.com/test?cursor='.$nextCursor);
});

test('paginator generates urls without trailing slash', function () {
    $p = new CursorPaginator($array = [['id' => 4], ['id' => 5], ['id' => 6]], 2, null,
        ['path' => 'http://website.com/test', 'parameters' => ['id']]);

    $nextCursor = (new Cursor(['id' => 5], true))->encode();

    expect($p->nextPageUrl())->toBe('http://website.com/test?cursor='.$nextCursor);
});

test('it retrieves the paginator options', function () {
    $p = new CursorPaginator($array = [['id' => 4], ['id' => 5], ['id' => 6]], 2, null,
        $options = ['path' => 'http://website.com/test', 'parameters' => ['id']]);

    expect($p->getOptions())->toBe($options);
});

test('paginator returns path', function () {
    $p = new CursorPaginator($array = [['id' => 4], ['id' => 5], ['id' => 6]], 2, null,
        $options = ['path' => 'http://website.com/test', 'parameters' => ['id']]);

    expect($p->path())->toBe('http://website.com/test');
});

test('it can transform paginator items', function () {
    $p = new CursorPaginator($array = [['id' => 4], ['id' => 5], ['id' => 6]], 2, null,
        $options = ['path' => 'http://website.com/test', 'parameters' => ['id']]);

    $p->through(function ($item) {
        $item['id'] = $item['id'] + 2;

        return $item;
    });

    expect($p)->toBeInstanceOf(CursorPaginator::class);
    expect($p->items())->toBe([['id' => 6], ['id' => 7]]);
});

test('cursor paginator on first and last page', function () {
    $paginator = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]], 2, null, [
        'parameters' => ['id'],
    ]);

    expect($paginator->onFirstPage())->toBeTrue();
    expect($paginator->onLastPage())->toBeFalse();

    $cursor = new Cursor(['id' => 3]);
    $paginator = new CursorPaginator([['id' => 3], ['id' => 4]], 2, $cursor, [
        'parameters' => ['id'],
    ]);

    expect($paginator->onFirstPage())->toBeFalse();
    expect($paginator->onLastPage())->toBeTrue();
});

test('it returns empty cursor when items are empty', function () {
    $cursor = new Cursor(['id' => 25], true);

    $p = new CursorPaginator(new Collection, 25, $cursor, [
        'path' => 'http://website.com/test',
        'cursorName' => 'cursor',
        'parameters' => ['id'],
    ]);

    expect($p)->toBeInstanceOf(CursorPaginator::class);

    expect($p->toArray())->toBe([
        'data' => [],
        'path' => 'http://website.com/test',
        'per_page' => 25,
        'next_cursor' => null,
        'next_page_url' => null,
        'prev_cursor' => null,
        'prev_page_url' => null,
    ]);
});

test('cursor paginator toJson', function () {
    $paginator = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]], 2, null);
    $results = $paginator->toJson();
    $expected = json_encode($paginator->toArray());

    expect(json_decode($results, true))->toEqual(json_decode($expected, true));
    expect($results)->toBe($expected);
});

test('cursor paginator toPrettyJson', function () {
    $paginator = new CursorPaginator([['id' => '1'], ['id' => '2'], ['id' => '3'], ['id' => '4']], 2, null);
    $results = $paginator->toPrettyJson();
    $expected = $paginator->toJson(JSON_PRETTY_PRINT);

    expect(json_decode($results, true))->toEqual(json_decode($expected, true));
    expect($results)->toBe($expected);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');

    $results = $paginator->toPrettyJson(JSON_NUMERIC_CHECK);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');
    expect($results)->toContain('"id": 1');
});
