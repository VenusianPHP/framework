<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Grammars\SQLiteGrammar;
use Mockery as m;

test('to raw sql', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('escape')->with('foo', false)->andReturn("'foo'");
    $grammar = new SQLiteGrammar($connection);

    $query = $grammar->substituteBindingsIntoRawSql(
        'select * from "users" where \'Hello\'\'World?\' IS NOT NULL AND "email" = ?',
        ['foo'],
    );

    $this->assertSame('select * from "users" where \'Hello\'\'World?\' IS NOT NULL AND "email" = \'foo\'', $query);
});
