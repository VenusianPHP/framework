<?php

use Voyager\Database\Capsule\Manager;
use Voyager\Database\Connection;
use Voyager\Database\Query\Expression;
use Voyager\Database\Query\Processors\SQLiteProcessor;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Schema\ForeignIdColumnDefinition;
use Voyager\Database\Schema\Grammars\SQLiteGrammar;
use Voyager\Database\Schema\SQLiteBuilder;
use Tests\Database\Fixtures\Enums\Foo;
use Mockery as m;

/**
 * Build a mocked connection wired up with the given schema grammar and
 * builder (or sensible defaults), for tests that only need a plain
 * connection stand-in.
 */
function sqliteGrammarConnection(
    ?SQLiteGrammar $grammar = null,
    ?SQLiteBuilder $builder = null,
    $prefix = ''
) {
    $connection = m::mock(Connection::class);
    $grammar ??= sqliteGrammarGrammar($connection);
    $builder ??= sqliteGrammarBuilder();

    return $connection
        ->shouldReceive('getTablePrefix')->andReturn($prefix)
        ->shouldReceive('getConfig')->andReturn(null)
        ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
        ->shouldReceive('getSchemaBuilder')->andReturn($builder)
        ->shouldReceive('getServerVersion')->andReturn('3.35')
        ->getMock();
}

function sqliteGrammarGrammar(?Connection $connection = null)
{
    return new SQLiteGrammar($connection ?? sqliteGrammarConnection());
}

function sqliteGrammarBuilder()
{
    return mock(SQLiteBuilder::class)
        ->makePartial()
        ->shouldReceive('getColumns')->andReturn([])
        ->shouldReceive('getIndexes')->andReturn([])
        ->shouldReceive('getForeignKeys')->andReturn([])
        ->getMock();
}

test('basic create table', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("id" integer primary key autoincrement not null, "email" varchar not null)', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $expected = [
        'alter table "users" add column "id" integer primary key autoincrement not null',
        'alter table "users" add column "email" varchar not null',
    ];
    $this->assertEquals($expected, $statements);
});

test('create temporary table', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->temporary();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create temporary table "users" ("id" integer primary key autoincrement not null, "email" varchar not null)', $statements[0]);
});

test('drop table', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->drop();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table "users"', $statements[0]);
});

test('drop table if exists', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dropIfExists();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table if exists "users"', $statements[0]);
});

test('drop unique', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dropUnique('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "foo"', $statements[0]);
});

test('drop index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dropIndex('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "foo"', $statements[0]);
});

test('drop index with schema', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'my_schema.users');
    $blueprint->dropIndex('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "my_schema"."foo"', $statements[0]);
});

test('drop column', function () {
    $db = new Manager;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'prefix_',
    ]);

    $schema = $db->getConnection()->getSchemaBuilder();

    $schema->create('users', function (Blueprint $table) {
        $table->string('email');
        $table->string('name');
    });

    $this->assertTrue($schema->hasTable('users'));
    $this->assertTrue($schema->hasColumn('users', 'name'));

    $schema->table('users', function (Blueprint $table) {
        $table->dropColumn('name');
    });

    $this->assertFalse($schema->hasColumn('users', 'name'));
});

test('drop spatial index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'geo');
    $blueprint->dropSpatialIndex(['coordinates']);
    $blueprint->toSql();
})->throws(RuntimeException::class, 'The database driver in use does not support spatial indexes.');

test('rename table', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->rename('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" rename to "foo"', $statements[0]);
});

test('rename index', function () {
    $db = new Manager;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'prefix_',
    ]);

    $schema = $db->getConnection()->getSchemaBuilder();

    $schema->create('users', function (Blueprint $table) {
        $table->string('name');
        $table->string('email');
    });

    $schema->table('users', function (Blueprint $table) {
        $table->index(['name', 'email'], 'index1');
    });

    $indexes = $schema->getIndexListing('users');

    $this->assertContains('index1', $indexes);
    $this->assertNotContains('index2', $indexes);

    $schema->table('users', function (Blueprint $table) {
        $table->renameIndex('index1', 'index2');
    });

    $this->assertFalse($schema->hasIndex('users', 'index1'));
    $this->assertTrue(collect($schema->getIndexes('users'))->contains(
        fn ($index) => $index['name'] === 'index2' && $index['columns'] === ['name', 'email']
    ));
});

test('adding primary key', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('foo')->primary();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("foo" varchar not null, primary key ("foo"))', $statements[0]);
});

test('adding foreign key', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('foo')->primary();
    $blueprint->string('order_id');
    $blueprint->foreign('order_id')->references('id')->on('orders');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("foo" varchar not null, "order_id" varchar not null, foreign key("order_id") references "orders"("id"), primary key ("foo"))', $statements[0]);
});

test('adding unique key', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create unique index "bar" on "users" ("foo")', $statements[0]);
});

test('adding index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "baz" on "users" ("foo", "bar")', $statements[0]);
});

test('adding unique key with schema', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'foo.users');
    $blueprint->unique('foo', 'bar');

    $this->assertSame(['create unique index "foo"."bar" on "users" ("foo")'], $blueprint->toSql());
});

test('adding index with schema', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'foo.users');
    $blueprint->index(['foo', 'bar'], 'baz');

    $this->assertSame(['create index "foo"."baz" on "users" ("foo", "bar")'], $blueprint->toSql());
});

test('adding spatial index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates');
    $blueprint->toSql();
})->throws(RuntimeException::class, 'The database driver in use does not support spatial indexes.');

test('adding fluent spatial index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates')->spatialIndex();
    $blueprint->toSql();
})->throws(RuntimeException::class, 'The database driver in use does not support spatial indexes.');

test('adding raw index', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->rawIndex('(function(column))', 'raw_index');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "raw_index" on "users" ((function(column)))', $statements[0]);
});

test('adding incrementing id', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->increments('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" integer primary key autoincrement not null', $statements[0]);
});

test('adding small incrementing id', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->smallIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" integer primary key autoincrement not null', $statements[0]);
});

test('adding medium incrementing id', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->mediumIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" integer primary key autoincrement not null', $statements[0]);
});

test('adding id', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->id();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" integer primary key autoincrement not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->id('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding foreign id', function () {
    $connection = sqliteGrammarConnection();
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getPostProcessor')->andReturn(new SQliteProcessor);
    $connection->shouldReceive('selectFromWriteConnection')->andReturn([]);
    $connection->shouldReceive('scalar')->andReturn('');

    $blueprint = new Blueprint($connection, 'users');
    $foreignId = $blueprint->foreignId('foo');
    $blueprint->foreignId('company_id')->constrained();
    $blueprint->foreignId('laravel_idea_id')->constrained();
    $blueprint->foreignId('team_id')->references('id')->on('teams');
    $blueprint->foreignId('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignId);
    $this->assertSame([
        'alter table "users" add column "foo" integer not null',
        'alter table "users" add column "company_id" integer not null',
        'create table "__temp__users" ("foo" integer not null, "company_id" integer not null, foreign key("company_id") references "companies"("id"))',
        'insert into "__temp__users" ("foo", "company_id") select "foo", "company_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "laravel_idea_id" integer not null',
        'create table "__temp__users" ("foo" integer not null, "company_id" integer not null, "laravel_idea_id" integer not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id") select "foo", "company_id", "laravel_idea_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "team_id" integer not null',
        'create table "__temp__users" ("foo" integer not null, "company_id" integer not null, "laravel_idea_id" integer not null, "team_id" integer not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"), foreign key("team_id") references "teams"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id", "team_id") select "foo", "company_id", "laravel_idea_id", "team_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "team_column_id" integer not null',
        'create table "__temp__users" ("foo" integer not null, "company_id" integer not null, "laravel_idea_id" integer not null, "team_id" integer not null, "team_column_id" integer not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"), foreign key("team_id") references "teams"("id"), foreign key("team_column_id") references "teams"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id", "team_id", "team_column_id") select "foo", "company_id", "laravel_idea_id", "team_id", "team_column_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
    ], $statements);
});

test('adding foreign id specifying index name in constraint', function () {
    $connection = sqliteGrammarConnection();
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getPostProcessor')->andReturn(new SQliteProcessor);
    $connection->shouldReceive('selectFromWriteConnection')->andReturn([]);
    $connection->shouldReceive('scalar')->andReturn('');

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->foreignId('company_id')->constrained(indexName: 'my_index');

    $statements = $blueprint->toSql();

    $this->assertSame([
        'alter table "users" add column "company_id" integer not null',
        'create table "__temp__users" ("company_id" integer not null, foreign key("company_id") references "companies"("id"))',
        'insert into "__temp__users" ("company_id") select "company_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
    ], $statements);
});

test('adding big incrementing id', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->bigIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" integer primary key autoincrement not null', $statements[0]);
});

test('adding string', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->string('foo', 100);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default('bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar default \'bar\'', $statements[0]);
});

test('adding text', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->text('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
});

test('adding big integer', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->bigInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->bigInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding integer', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->integer('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->integer('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding medium integer', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding tiny integer', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding small integer', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->smallInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->smallInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer primary key autoincrement not null', $statements[0]);
});

test('adding float', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->float('foo', 5);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" float not null', $statements[0]);
});

test('adding double', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->double('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" double not null', $statements[0]);
});

test('adding decimal', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->decimal('foo', 5, 2);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" numeric not null', $statements[0]);
});

test('adding boolean', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->boolean('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" tinyint(1) not null', $statements[0]);
});

test('adding enum', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->enum('role', ['member', 'admin']);
    $blueprint->enum('status', Foo::cases());
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table "users" add column "role" varchar check ("role" in (\'member\', \'admin\')) not null', $statements[0]);
    $this->assertSame('alter table "users" add column "status" varchar check ("status" in (\'bar\')) not null', $statements[1]);
});

test('adding json', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->json('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
});

test('adding native json', function () {
    $connection = m::mock(Connection::class);
    $connection
        ->shouldReceive('getTablePrefix')->andReturn('')
        ->shouldReceive('getConfig')->once()->with('use_native_json')->andReturn(true)
        ->shouldReceive('getSchemaGrammar')->andReturn(sqliteGrammarGrammar($connection))
        ->shouldReceive('getSchemaBuilder')->andReturn(sqliteGrammarBuilder())
        ->shouldReceive('getServerVersion')->andReturn('3.35')
        ->getMock();

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->json('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" json not null', $statements[0]);
});

test('adding jsonb', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->jsonb('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
});

test('adding native jsonb', function () {
    $connection = m::mock(Connection::class);
    $connection
        ->shouldReceive('getTablePrefix')->andReturn('')
        ->shouldReceive('getConfig')->once()->with('use_native_jsonb')->andReturn(true)
        ->shouldReceive('getSchemaGrammar')->andReturn(sqliteGrammarGrammar($connection))
        ->shouldReceive('getSchemaBuilder')->andReturn(sqliteGrammarBuilder())
        ->shouldReceive('getServerVersion')->andReturn('3.35')
        ->getMock();

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->jsonb('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" jsonb not null', $statements[0]);
});

test('adding date', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->date('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" date not null', $statements[0]);
});

test('adding date with default current', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->date('foo')->useCurrent();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" date not null default CURRENT_DATE', $statements[0]);
});

test('adding year', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->year('birth_year');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "birth_year" integer not null', $statements[0]);
});

test('adding year with default current', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->year('birth_year')->useCurrent();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "birth_year" integer not null default (CAST(strftime(\'%Y\', \'now\') AS INTEGER))', $statements[0]);
});

test('adding date time', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dateTime('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding date time with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dateTime('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding date time tz', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dateTimeTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding date time tz with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->dateTimeTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding time', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->time('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
});

test('adding time with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->time('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
});

test('adding time tz', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timeTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
});

test('adding time tz with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timeTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
});

test('adding timestamp', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestamp('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding timestamp with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestamp('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding timestamp tz', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestampTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding timestamp tz with precision', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestampTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
});

test('adding timestamps', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestamps();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertEquals([
        'alter table "users" add column "created_at" datetime',
        'alter table "users" add column "updated_at" datetime',
    ], $statements);
});

test('adding timestamps tz', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->timestampsTz();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertEquals([
        'alter table "users" add column "created_at" datetime',
        'alter table "users" add column "updated_at" datetime',
    ], $statements);
});

test('adding remember token', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->rememberToken();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "remember_token" varchar', $statements[0]);
});

test('adding binary', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->binary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" blob not null', $statements[0]);
});

test('adding uuid', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->uuid('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
});

test('adding uuid defaults column name', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->uuid();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "uuid" varchar not null', $statements[0]);
});

test('adding foreign uuid', function () {
    $connection = sqliteGrammarConnection();
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('getPostProcessor')->andReturn(new SQliteProcessor);
    $connection->shouldReceive('selectFromWriteConnection')->andReturn([]);
    $connection->shouldReceive('scalar')->andReturn('');

    $blueprint = new Blueprint($connection, 'users');
    $foreignUuid = $blueprint->foreignUuid('foo');
    $blueprint->foreignUuid('company_id')->constrained();
    $blueprint->foreignUuid('laravel_idea_id')->constrained();
    $blueprint->foreignUuid('team_id')->references('id')->on('teams');
    $blueprint->foreignUuid('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignUuid);
    $this->assertSame([
        'alter table "users" add column "foo" varchar not null',
        'alter table "users" add column "company_id" varchar not null',
        'create table "__temp__users" ("foo" varchar not null, "company_id" varchar not null, foreign key("company_id") references "companies"("id"))',
        'insert into "__temp__users" ("foo", "company_id") select "foo", "company_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "laravel_idea_id" varchar not null',
        'create table "__temp__users" ("foo" varchar not null, "company_id" varchar not null, "laravel_idea_id" varchar not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id") select "foo", "company_id", "laravel_idea_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "team_id" varchar not null',
        'create table "__temp__users" ("foo" varchar not null, "company_id" varchar not null, "laravel_idea_id" varchar not null, "team_id" varchar not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"), foreign key("team_id") references "teams"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id", "team_id") select "foo", "company_id", "laravel_idea_id", "team_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
        'alter table "users" add column "team_column_id" varchar not null',
        'create table "__temp__users" ("foo" varchar not null, "company_id" varchar not null, "laravel_idea_id" varchar not null, "team_id" varchar not null, "team_column_id" varchar not null, foreign key("company_id") references "companies"("id"), foreign key("laravel_idea_id") references "laravel_ideas"("id"), foreign key("team_id") references "teams"("id"), foreign key("team_column_id") references "teams"("id"))',
        'insert into "__temp__users" ("foo", "company_id", "laravel_idea_id", "team_id", "team_column_id") select "foo", "company_id", "laravel_idea_id", "team_id", "team_column_id" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
    ], $statements);
});

test('adding ip address', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->ipAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
});

test('adding ip address defaults column name', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->ipAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "ip_address" varchar not null', $statements[0]);
});

test('adding mac address', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->macAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
});

test('adding mac address defaults column name', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->macAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "mac_address" varchar not null', $statements[0]);
});

test('adding geometry', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
});

test('adding generated column', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'products');
    $blueprint->create();
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs('"price" - 5');
    $blueprint->integer('discounted_stored')->storedAs('"price" - 5');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "products" ("price" integer not null, "discounted_virtual" integer as ("price" - 5), "discounted_stored" integer as ("price" - 5) stored)', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs('"price" - 5')->nullable(false);
    $blueprint->integer('discounted_stored')->storedAs('"price" - 5')->nullable(false);
    $statements = $blueprint->toSql();

    $this->assertCount(3, $statements);
    $expected = [
        'alter table "products" add column "price" integer not null',
        'alter table "products" add column "discounted_virtual" integer not null as ("price" - 5)',
        'alter table "products" add column "discounted_stored" integer not null as ("price" - 5) stored',
    ];
    $this->assertSame($expected, $statements);
});

test('adding generated column by expression', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'products');
    $blueprint->create();
    $blueprint->integer('price');
    $blueprint->integer('discounted_virtual')->virtualAs(new Expression('"price" - 5'));
    $blueprint->integer('discounted_stored')->storedAs(new Expression('"price" - 5'));
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "products" ("price" integer not null, "discounted_virtual" integer as ("price" - 5), "discounted_stored" integer as ("price" - 5) stored)', $statements[0]);
});

test('grammars are macroable', function () {
    // compileReplace macro.
    sqliteGrammarGrammar()::macro('compileReplace', function () {
        return true;
    });

    $c = sqliteGrammarGrammar()::compileReplace();

    $this->assertTrue($c);
});

test('create table with virtual as column', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_column');
    $blueprint->string('my_other_column')->virtualAs('my_column');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_column" varchar not null, "my_other_column" varchar as (my_column))', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->virtualAsJson('my_json_column->some_attribute');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_json_column" varchar not null, "my_other_column" varchar as (json_extract("my_json_column", \'$."some_attribute"\')))', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->virtualAsJson('my_json_column->some_attribute->nested');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_json_column" varchar not null, "my_other_column" varchar as (json_extract("my_json_column", \'$."some_attribute"."nested"\')))', $statements[0]);
});

test('create table with virtual as column when json column has array key', function () {
    $conn = sqliteGrammarConnection();
    $conn->shouldReceive('getConfig')->andReturn(null);

    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->string('my_json_column')->virtualAsJson('my_json_column->foo[0][1]');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("create table \"users\" (\"my_json_column\" varchar as (json_extract(\"my_json_column\", '$.\"foo\"[0][1]')))", $statements[0]);
});

test('create table with stored as column', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_column');
    $blueprint->string('my_other_column')->storedAs('my_column');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_column" varchar not null, "my_other_column" varchar as (my_column) stored)', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->storedAsJson('my_json_column->some_attribute');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_json_column" varchar not null, "my_other_column" varchar as (json_extract("my_json_column", \'$."some_attribute"\')) stored)', $statements[0]);

    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->string('my_json_column');
    $blueprint->string('my_other_column')->storedAsJson('my_json_column->some_attribute->nested');

    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("my_json_column" varchar not null, "my_other_column" varchar as (json_extract("my_json_column", \'$."some_attribute"."nested"\')) stored)', $statements[0]);
});

test('dropping columns works', function () {
    $blueprint = new Blueprint(sqliteGrammarConnection(), 'users', function ($table) {
        $table->dropColumn('name');
    });

    $this->assertEquals(['alter table "users" drop column "name"'], $blueprint->toSql());
});

test('renaming and changing columns work', function () {
    $builder = mock(SQLiteBuilder::class)
        ->makePartial()
        ->shouldReceive('getColumns')->andReturn([
            ['name' => 'name', 'type_name' => 'varchar', 'type' => 'varchar', 'collation' => null, 'nullable' => false, 'default' => null, 'auto_increment' => false, 'comment' => null, 'generation' => null],
            ['name' => 'age', 'type_name' => 'varchar', 'type' => 'varchar', 'collation' => null, 'nullable' => false, 'default' => null, 'auto_increment' => false, 'comment' => null, 'generation' => null],
        ])
        ->shouldReceive('getIndexes')->andReturn([])
        ->shouldReceive('getForeignKeys')->andReturn([])
        ->getMock();

    $connection = sqliteGrammarConnection(builder: $builder);
    $connection->shouldReceive('scalar')->with('pragma foreign_keys')->andReturn(false);

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->renameColumn('name', 'first_name');
    $blueprint->integer('age')->change();

    $this->assertEquals([
        'alter table "users" rename column "name" to "first_name"',
        'create table "__temp__users" ("first_name" varchar not null, "age" integer not null)',
        'insert into "__temp__users" ("first_name", "age") select "first_name", "age" from "users"',
        'drop table "users"',
        'alter table "__temp__users" rename to "users"',
    ], $blueprint->toSql());
});

test('renaming and changing columns work with schema', function () {
    $builder = mock(SQLiteBuilder::class)
        ->makePartial()
        ->shouldReceive('getColumns')->andReturn([
            ['name' => 'name', 'type_name' => 'varchar', 'type' => 'varchar', 'collation' => null, 'nullable' => false, 'default' => null, 'auto_increment' => false, 'comment' => null, 'generation' => null],
            ['name' => 'age', 'type_name' => 'varchar', 'type' => 'varchar', 'collation' => null, 'nullable' => false, 'default' => null, 'auto_increment' => false, 'comment' => null, 'generation' => null],
        ])
        ->shouldReceive('getIndexes')->andReturn([])
        ->shouldReceive('getForeignKeys')->andReturn([])
        ->getMock();

    $connection = sqliteGrammarConnection(builder: $builder);
    $connection->shouldReceive('scalar')->with('pragma foreign_keys')->andReturn(false);

    $blueprint = new Blueprint($connection, 'my_schema.users');
    $blueprint->renameColumn('name', 'first_name');
    $blueprint->integer('age')->change();

    $this->assertEquals([
        'alter table "my_schema"."users" rename column "name" to "first_name"',
        'create table "my_schema"."__temp__users" ("first_name" varchar not null, "age" integer not null)',
        'insert into "my_schema"."__temp__users" ("first_name", "age") select "first_name", "age" from "my_schema"."users"',
        'drop table "my_schema"."users"',
        'alter table "my_schema"."__temp__users" rename to "users"',
    ], $blueprint->toSql());
});
