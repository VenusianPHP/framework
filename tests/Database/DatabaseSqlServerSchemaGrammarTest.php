<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Expression;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Schema\ForeignIdColumnDefinition;
use Voyager\Database\Schema\Grammars\SqlServerGrammar;
use Voyager\Database\Schema\SqlServerBuilder;
use Tests\Database\Fixtures\Enums\Foo;
use Mockery as m;

function sqlServerGrammarConnection(
    ?SqlServerGrammar $grammar = null,
    ?SqlServerBuilder $builder = null,
    string $prefix = ''
) {
    $connection = m::mock(Connection::class)
        ->shouldReceive('getTablePrefix')->andReturn($prefix)
        ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
        ->getMock();

    $grammar ??= sqlServerGrammarGrammar($connection);
    $builder ??= sqlServerGrammarBuilder();

    return $connection
        ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
        ->shouldReceive('getSchemaBuilder')->andReturn($builder)
        ->getMock();
}

function sqlServerGrammarGrammar(?Connection $connection = null)
{
    return new SqlServerGrammar($connection ?? sqlServerGrammarConnection());
}

function sqlServerGrammarBuilder()
{
    return mock(SqlServerBuilder::class);
}

test('basic create table', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("id" int not null identity primary key, "email" nvarchar(255) not null)', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add "id" int not null identity primary key',
        'alter table "users" add "email" nvarchar(255) not null',
    ], $statements);

    $conn = sqlServerGrammarConnection(prefix: 'prefix_');
    $blueprint = new Blueprint($conn, 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "prefix_users" ("id" int not null identity primary key, "email" nvarchar(255) not null)', $statements[0]);
});

test('create temporary table', function () {
    $connection = sqlServerGrammarConnection();
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $blueprint = new Blueprint($connection, 'users');
    $blueprint->create();
    $blueprint->temporary();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "#users" ("id" int not null identity primary key, "email" nvarchar(255) not null)', $statements[0]);
});

test('create temporary table with prefix', function () {
    $connection = sqlServerGrammarConnection(prefix: 'prefix_');
    $blueprint = new Blueprint($connection, 'users');
    $blueprint->create();
    $blueprint->temporary();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "#prefix_users" ("id" int not null identity primary key, "email" nvarchar(255) not null)', $statements[0]);
});

test('drop table', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->drop();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table "users"', $statements[0]);

    $conn = sqlServerGrammarConnection(prefix: 'prefix_');
    $blueprint = new Blueprint($conn, 'users');
    $blueprint->drop();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table "prefix_users"', $statements[0]);
});

test('drop table if exists', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropIfExists();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('if object_id(N\'"users"\', \'U\') is not null drop table "users"', $statements[0]);

    $conn = sqlServerGrammarConnection(prefix: 'prefix_');
    $blueprint = new Blueprint($conn, 'users');
    $blueprint->dropIfExists();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('if object_id(N\'"prefix_users"\', \'U\') is not null drop table "prefix_users"', $statements[0]);
});

test('drop column', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropColumn('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertStringContainsString('alter table "users" drop column "foo"', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropColumn(['foo', 'bar']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertStringContainsString('alter table "users" drop column "foo", "bar"', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropColumn('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertStringContainsString('alter table "users" drop column "foo", "bar"', $statements[0]);
});

test('drop column drops creates sql to drop default constraints', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'foo');
    $blueprint->dropColumn('bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame("DECLARE @sql NVARCHAR(MAX) = '';SELECT @sql += 'ALTER TABLE \"foo\" DROP CONSTRAINT ' + OBJECT_NAME([default_object_id]) + ';' FROM sys.columns WHERE [object_id] = OBJECT_ID(N'\"foo\"') AND [name] in ('bar') AND [default_object_id] <> 0;EXEC(@sql);alter table \"foo\" drop column \"bar\"", $statements[0]);
});

test('drop primary', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropPrimary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop constraint "foo"', $statements[0]);
});

test('drop unique', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropUnique('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "foo" on "users"', $statements[0]);
});

test('drop index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropIndex('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "foo" on "users"', $statements[0]);
});

test('drop spatial index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'geo');
    $blueprint->dropSpatialIndex(['coordinates']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "geo_coordinates_spatialindex" on "geo"', $statements[0]);
});

test('drop foreign', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropForeign('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop constraint "foo"', $statements[0]);
});

test('drop constrained foreign id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropConstrainedForeignId('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table "users" drop constraint "users_foo_foreign"', $statements[0]);
    $this->assertSame('DECLARE @sql NVARCHAR(MAX) = \'\';SELECT @sql += \'ALTER TABLE "users" DROP CONSTRAINT \' + OBJECT_NAME([default_object_id]) + \';\' FROM sys.columns WHERE [object_id] = OBJECT_ID(N\'"users"\') AND [name] in (\'foo\') AND [default_object_id] <> 0;EXEC(@sql);alter table "users" drop column "foo"', $statements[1]);
});

test('drop timestamps', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropTimestamps();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertStringContainsString('alter table "users" drop column "created_at", "updated_at"', $statements[0]);
});

test('drop timestamps tz', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dropTimestampsTz();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertStringContainsString('alter table "users" drop column "created_at", "updated_at"', $statements[0]);
});

test('drop morphs', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'photos');
    $blueprint->dropMorphs('imageable');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('drop index "photos_imageable_type_imageable_id_index" on "photos"', $statements[0]);
    $this->assertStringContainsString('alter table "photos" drop column "imageable_type", "imageable_id"', $statements[1]);
});

test('rename table', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->rename('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('sp_rename N\'"users"\', "foo"', $statements[0]);
});

test('rename index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->renameIndex('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('sp_rename N\'"users"."foo"\', "bar", N\'INDEX\'', $statements[0]);
});

test('adding primary key', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->primary('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "bar" primary key ("foo")', $statements[0]);
});

test('adding unique key', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create unique index "bar" on "users" ("foo")', $statements[0]);
});

test('adding unique key online', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create unique index "bar" on "users" ("foo") with (online = on)', $statements[0]);
});

test('adding index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "baz" on "users" ("foo", "bar")', $statements[0]);
});

test('adding index online', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "baz" on "users" ("foo", "bar") with (online = on)', $statements[0]);
});

test('adding spatial index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create spatial index "geo_coordinates_spatialindex" on "geo" ("coordinates")', $statements[0]);
});

test('adding fluent spatial index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point')->spatialIndex();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create spatial index "geo_coordinates_spatialindex" on "geo" ("coordinates")', $statements[1]);
});

test('adding raw index', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->rawIndex('(function(column))', 'raw_index');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "raw_index" on "users" ((function(column)))', $statements[0]);
});

test('adding incrementing id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->increments('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "id" int not null identity primary key', $statements[0]);
});

test('adding small incrementing id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->smallIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "id" smallint not null identity primary key', $statements[0]);
});

test('adding medium incrementing id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->mediumIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "id" int not null identity primary key', $statements[0]);
});

test('adding id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->id();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "id" bigint not null identity primary key', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->id('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" bigint not null identity primary key', $statements[0]);
});

test('adding foreign id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $foreignId = $blueprint->foreignId('foo');
    $blueprint->foreignId('company_id')->constrained();
    $blueprint->foreignId('laravel_idea_id')->constrained();
    $blueprint->foreignId('team_id')->references('id')->on('teams');
    $blueprint->foreignId('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignId);
    $this->assertSame([
        'alter table "users" add "foo" bigint not null',
        'alter table "users" add "company_id" bigint not null',
        'alter table "users" add constraint "users_company_id_foreign" foreign key ("company_id") references "companies" ("id")',
        'alter table "users" add "laravel_idea_id" bigint not null',
        'alter table "users" add constraint "users_laravel_idea_id_foreign" foreign key ("laravel_idea_id") references "laravel_ideas" ("id")',
        'alter table "users" add "team_id" bigint not null',
        'alter table "users" add constraint "users_team_id_foreign" foreign key ("team_id") references "teams" ("id")',
        'alter table "users" add "team_column_id" bigint not null',
        'alter table "users" add constraint "users_team_column_id_foreign" foreign key ("team_column_id") references "teams" ("id")',
    ], $statements);
});

test('adding foreign id specifying index name in constraint', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->foreignId('company_id')->constrained(indexName: 'my_index');
    $statements = $blueprint->toSql();
    $this->assertSame([
        'alter table "users" add "company_id" bigint not null',
        'alter table "users" add constraint "my_index" foreign key ("company_id") references "companies" ("id")',
    ], $statements);
});

test('adding big incrementing id', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->bigIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "id" bigint not null identity primary key', $statements[0]);
});

test('adding string', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(255) not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->string('foo', 100);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(100) not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default('bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(100) null default \'bar\'', $statements[0]);
});

test('adding text', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->text('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(max) not null', $statements[0]);
});

test('adding big integer', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->bigInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" bigint not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->bigInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" bigint not null identity primary key', $statements[0]);
});

test('adding integer', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->integer('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" int not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->integer('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" int not null identity primary key', $statements[0]);
});

test('adding medium integer', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" int not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" int not null identity primary key', $statements[0]);
});

test('adding tiny integer', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" tinyint not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" tinyint not null identity primary key', $statements[0]);
});

test('adding small integer', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->smallInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" smallint not null', $statements[0]);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->smallInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" smallint not null identity primary key', $statements[0]);
});

test('adding float', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->float('foo', 5);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" float(5) not null', $statements[0]);
});

test('adding double', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->double('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" double precision not null', $statements[0]);
});

test('adding decimal', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->decimal('foo', 5, 2);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" decimal(5, 2) not null', $statements[0]);
});

test('adding boolean', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->boolean('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" bit not null', $statements[0]);
});

test('adding enum', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->enum('role', ['member', 'admin']);
    $blueprint->enum('status', Foo::cases());
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table "users" add "role" nvarchar(255) check ("role" in (N\'member\', N\'admin\')) not null', $statements[0]);
    $this->assertSame('alter table "users" add "status" nvarchar(255) check ("status" in (N\'bar\')) not null', $statements[1]);
});

test('adding json', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->json('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(max) not null', $statements[0]);
});

test('adding jsonb', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->jsonb('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(max) not null', $statements[0]);
});

test('adding date', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->date('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" date not null', $statements[0]);
});

test('adding date with default current', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->date('foo')->useCurrent();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" date not null default CAST(GETDATE() AS DATE)', $statements[0]);
});

test('adding year', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->year('birth_year');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "birth_year" int not null', $statements[0]);
});

test('adding year with default current', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->year('birth_year')->useCurrent();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "birth_year" int not null default CAST(YEAR(GETDATE()) AS INTEGER)', $statements[0]);
});

test('adding date time', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dateTime('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetime not null', $statements[0]);
});

test('adding date time with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dateTime('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetime2(1) not null', $statements[0]);
});

test('adding date time tz', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dateTimeTz('foo');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" datetimeoffset not null', $statements[0]);
});

test('adding date time tz with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->dateTimeTz('foo', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" datetimeoffset(1) not null', $statements[0]);
});

test('adding time', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->time('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" time not null', $statements[0]);
});

test('adding time with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->time('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" time(1) not null', $statements[0]);
});

test('adding time tz', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timeTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" time not null', $statements[0]);
});

test('adding time tz with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timeTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" time(1) not null', $statements[0]);
});

test('adding timestamp', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestamp('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetime not null', $statements[0]);
});

test('adding timestamp with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestamp('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetime2(1) not null', $statements[0]);
});

test('adding timestamp tz', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestampTz('created_at');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetimeoffset not null', $statements[0]);
});

test('adding timestamp tz with precision', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestampTz('created_at', 1);
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "created_at" datetimeoffset(1) not null', $statements[0]);
});

test('adding timestamps', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestamps();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add "created_at" datetime null',
        'alter table "users" add "updated_at" datetime null',
    ], $statements);
});

test('adding timestamps tz', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->timestampsTz();
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add "created_at" datetimeoffset null',
        'alter table "users" add "updated_at" datetimeoffset null',
    ], $statements);
});

test('adding remember token', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->rememberToken();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "remember_token" nvarchar(100) null', $statements[0]);
});

test('adding binary', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->binary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" varbinary(max) not null', $statements[0]);
});

test('adding uuid', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->uuid('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" uniqueidentifier not null', $statements[0]);
});

test('adding uuid defaults column name', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->uuid();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "uuid" uniqueidentifier not null', $statements[0]);
});

test('adding foreign uuid', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $foreignId = $blueprint->foreignUuid('foo');
    $blueprint->foreignUuid('company_id')->constrained();
    $blueprint->foreignUuid('laravel_idea_id')->constrained();
    $blueprint->foreignUuid('team_id')->references('id')->on('teams');
    $blueprint->foreignUuid('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignId);
    $this->assertSame([
        'alter table "users" add "foo" uniqueidentifier not null',
        'alter table "users" add "company_id" uniqueidentifier not null',
        'alter table "users" add constraint "users_company_id_foreign" foreign key ("company_id") references "companies" ("id")',
        'alter table "users" add "laravel_idea_id" uniqueidentifier not null',
        'alter table "users" add constraint "users_laravel_idea_id_foreign" foreign key ("laravel_idea_id") references "laravel_ideas" ("id")',
        'alter table "users" add "team_id" uniqueidentifier not null',
        'alter table "users" add constraint "users_team_id_foreign" foreign key ("team_id") references "teams" ("id")',
        'alter table "users" add "team_column_id" uniqueidentifier not null',
        'alter table "users" add constraint "users_team_column_id_foreign" foreign key ("team_column_id") references "teams" ("id")',
    ], $statements);
});

test('adding ip address', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->ipAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(45) not null', $statements[0]);
});

test('adding ip address defaults column name', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->ipAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "ip_address" nvarchar(45) not null', $statements[0]);
});

test('adding mac address', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->macAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "foo" nvarchar(17) not null', $statements[0]);
});

test('adding mac address defaults column name', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'users');
    $blueprint->macAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add "mac_address" nvarchar(17) not null', $statements[0]);
});

test('adding geometry', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add "coordinates" geometry not null', $statements[0]);
});

test('adding geography', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'geo');
    $blueprint->geography('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add "coordinates" geography not null', $statements[0]);
});

test('adding generated column', function () {
    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->computed('discounted_virtual', 'price - 5');
    $blueprint->computed('discounted_stored', 'price - 5')->persisted();
    $statements = $blueprint->toSql();
    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table "products" add "price" int not null',
        'alter table "products" add "discounted_virtual" as (price - 5)',
        'alter table "products" add "discounted_stored" as (price - 5) persisted',
    ], $statements);

    $blueprint = new Blueprint(sqlServerGrammarConnection(), 'products');
    $blueprint->integer('price');
    $blueprint->computed('discounted_virtual', new Expression('price - 5'));
    $blueprint->computed('discounted_stored', new Expression('price - 5'))->persisted();
    $statements = $blueprint->toSql();
    $this->assertCount(3, $statements);
    $this->assertSame([
        'alter table "products" add "price" int not null',
        'alter table "products" add "discounted_virtual" as (price - 5)',
        'alter table "products" add "discounted_stored" as (price - 5) persisted',
    ], $statements);
});

test('grammars are macroable', function () {
    // compileReplace macro.
    sqlServerGrammarGrammar()::macro('compileReplace', function () {
        return true;
    });

    $c = sqlServerGrammarGrammar()::compileReplace();

    $this->assertTrue($c);
});

test('quote string', function () {
    $this->assertSame("N'中文測試'", sqlServerGrammarGrammar()->quoteString('中文測試'));
});

test('quote string on array', function () {
    $this->assertSame("N'中文', N'測試'", sqlServerGrammarGrammar()->quoteString(['中文', '測試']));
});

test('create database', function () {
    $statement = sqlServerGrammarGrammar()->compileCreateDatabase('my_database_a');

    $this->assertSame(
        'create database "my_database_a"',
        $statement
    );

    $statement = sqlServerGrammarGrammar()->compileCreateDatabase('my_database_b');

    $this->assertSame(
        'create database "my_database_b"',
        $statement
    );
});

test('drop database if exists', function () {
    $statement = sqlServerGrammarGrammar()->compileDropDatabaseIfExists('my_database_a');

    $this->assertSame(
        'drop database if exists "my_database_a"',
        $statement
    );

    $statement = sqlServerGrammarGrammar()->compileDropDatabaseIfExists('my_database_b');

    $this->assertSame(
        'drop database if exists "my_database_b"',
        $statement
    );
});
