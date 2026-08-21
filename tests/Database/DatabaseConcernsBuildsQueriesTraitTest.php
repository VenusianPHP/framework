<?php

namespace Tests\Database;

use Voyager\Database\Concerns\BuildsQueries;

test('tap callback instance', function () {
    $mock = new class
    {
        use BuildsQueries;
    };

    $mock->tap(function ($builder) use ($mock) {
        expect($builder)->toEqual($mock);
    });
});
