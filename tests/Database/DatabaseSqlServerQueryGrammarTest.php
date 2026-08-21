<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Grammars\SqlServerGrammar;
use Mockery as m;

test('to raw sql', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('escape')->with('foo', false)->andReturn("'foo'");
    $grammar = new SqlServerGrammar($connection);

    $query = $grammar->substituteBindingsIntoRawSql(
        "select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = ?",
        ['foo'],
    );

    expect($query)->toBe("select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = 'foo'");
});
