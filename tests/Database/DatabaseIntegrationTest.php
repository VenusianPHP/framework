<?php

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Events\QueryExecuted;
use Voyager\Events\Dispatcher;

beforeEach(function () {
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $db->setAsGlobal();
    $db->setEventDispatcher(new Dispatcher);
});

test('query executed to raw sql', function () {
    $connection = DB::connection();

    $connection->listen(function (QueryExecuted $query) use (&$queryExecuted): void {
        $queryExecuted = $query;
    });

    $connection->select('select ?', [true]);

    $this->assertInstanceOf(QueryExecuted::class, $queryExecuted);
    $this->assertSame('select ?', $queryExecuted->sql);
    $this->assertSame([true], $queryExecuted->bindings);
    $this->assertSame('select 1', $queryExecuted->toRawSql());
});
