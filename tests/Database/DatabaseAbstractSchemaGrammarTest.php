<?php

namespace Tests\Database;

use Voyager\Database\Connection;
use Voyager\Database\Schema\Grammars\Grammar;
use Mockery as m;

test('create database', function () {
    $connection = m::mock(Connection::class);
    $grammar = new class($connection) extends Grammar {
    };

    expect($grammar->compileCreateDatabase('foo'))->toBe('create database "foo"');
});

test('drop database if exists', function () {
    $connection = m::mock(Connection::class);
    $grammar = new class($connection) extends Grammar {
    };

    expect($grammar->compileDropDatabaseIfExists('foo'))->toBe('drop database if exists "foo"');
});
