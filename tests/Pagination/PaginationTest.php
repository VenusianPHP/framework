<?php

use Voyager\Pagination\Cursor;
use Voyager\Pagination\Paginator;
use Voyager\Pagination\UrlWindow;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\LengthAwarePaginator;
use Voyager\Contracts\Pagination\Paginator as PaginatorContract;
use Voyager\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Voyager\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;

it('pages a known total, and builds page urls on its path', function () {
    $paginator = new LengthAwarePaginator(['c', 'd'], 5, 2, 2, ['path' => '/items']);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginatorContract::class)
        ->and($paginator->items())->toBe(['c', 'd'])
        ->and($paginator->total())->toBe(5)
        ->and($paginator->lastPage())->toBe(3)
        ->and($paginator->firstItem())->toBe(3)
        ->and($paginator->lastItem())->toBe(4)
        ->and($paginator->hasMorePages())->toBeTrue()
        ->and($paginator->url(3))->toBe('/items?page=3')
        ->and($paginator->previousPageUrl())->toBe('/items?page=1')
        ->and($paginator->nextPageUrl())->toBe('/items?page=3');
});

it('keeps the query string it is given on every page url', function () {
    $paginator = new LengthAwarePaginator(['a'], 10, 1, 1, ['path' => '/items']);

    expect($paginator->appends(['sort' => 'name'])->url(2))->toBe('/items?sort=name&page=2');
});

it('lays out its fields as an array, the way an API answers with it', function () {
    $array = new LengthAwarePaginator(['a', 'b'], 3, 2, 1, ['path' => '/items'])->toArray();

    expect(array_keys($array))->toContain('current_page', 'data', 'first_page_url', 'from', 'last_page', 'next_page_url', 'per_page', 'prev_page_url', 'to', 'total', 'links')
        ->and($array['data'])->toBe(['a', 'b'])
        ->and($array['total'])->toBe(3);
});

it('pages without a total, knowing there is more from the one extra item it was handed', function () {
    $more = new Paginator(['a', 'b', 'c'], 2, 1, ['path' => '/items']);
    $last = new Paginator(['a', 'b'], 2, 2, ['path' => '/items']);

    expect($more)->toBeInstanceOf(PaginatorContract::class)
        ->and($more->items())->toBe(['a', 'b'])
        ->and($more->hasMorePages())->toBeTrue()
        ->and($more->nextPageUrl())->toBe('/items?page=2')
        ->and($last->hasMorePages())->toBeFalse()
        ->and($last->nextPageUrl())->toBeNull();
});

it('encodes a cursor and reads it back', function () {
    $cursor = new Cursor(['id' => 42, 'created_at' => '2026-09-29'], pointsToNextItems: true);

    $read = Cursor::fromEncoded($cursor->encode());

    expect($read->parameter('id'))->toBe(42)
        ->and($read->parameter('created_at'))->toBe('2026-09-29')
        ->and($read->pointsToNextItems())->toBeTrue()
        ->and(Cursor::fromEncoded('not a cursor'))->toBeNull();
});

it('pages by cursor, pointing the next page after its last item', function () {
    $paginator = new CursorPaginator(
        [['id' => 1], ['id' => 2], ['id' => 3]],
        2,
        null,
        ['path' => '/items', 'parameters' => ['id']],
    );

    expect($paginator)->toBeInstanceOf(CursorPaginatorContract::class)
        ->and(array_column($paginator->items(), 'id'))->toBe([1, 2])
        ->and($paginator->hasMorePages())->toBeTrue()
        ->and($paginator->nextCursor()->parameter('id'))->toBe(2)
        ->and($paginator->nextPageUrl())->toStartWith('/items?cursor=');
});

it('windows a long page list around the current page', function () {
    $window = UrlWindow::make(new LengthAwarePaginator(range(1, 10), 200, 10, 10, ['path' => '/items']));

    expect(array_keys($window['first']))->toBe([1, 2])
        ->and(array_keys($window['slider']))->toBe([7, 8, 9, 10, 11, 12, 13])
        ->and(array_keys($window['last']))->toBe([19, 20]);
});
