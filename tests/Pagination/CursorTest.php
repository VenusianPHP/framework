<?php

use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\Pagination\Cursor;

test('it can encode and decode successfully', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => Carbon::now()->toDateTimeString(),
    ], true);

    expect(Cursor::fromEncoded($cursor->encode()))->toEqual($cursor);
});

test('it can get params', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => ($now = Carbon::now()->toDateTimeString()),
    ], true);

    expect($cursor->parameters(['created_at', 'id']))->toEqual([$now, 422]);
});

test('it can get param', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => ($now = Carbon::now()->toDateTimeString()),
    ], true);

    expect($cursor->parameter('created_at'))->toEqual($now);
});
