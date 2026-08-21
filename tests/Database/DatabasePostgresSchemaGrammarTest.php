<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Expression;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Schema\Builder;
use Voyager\Database\Schema\ForeignIdColumnDefinition;
use Voyager\Database\Schema\Grammars\PostgresGrammar;
use Voyager\Database\Schema\PostgresBuilder;
use Tests\Database\Fixtures\Enums\Foo;
use Mockery as m;

test('basic create table', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email');
    $blueprint->string('name')->collation('nb_NO.utf8');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create table "users" ("id" serial not null primary key, "email" varchar(255) not null, "name" varchar(255) collate "nb_NO.utf8" not null)', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add column "id" serial not null primary key',
        'alter table "users" add column "email" varchar(255) not null',
    ], $statements);
});

test('adding vector', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'embeddings');
    $blueprint->vector('embedding', 384);
    $statements = $blueprint->toSql(postgresSchemaGrammarConnection(), postgresSchemaGrammarGrammar());

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "embeddings" add column "embedding" vector(384) not null', $statements[0]);
});

test('adding tsvector column', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'test');
    $blueprint->tsvector('search_vector');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "test" add column "search_vector" tsvector not null', $statements[0]);
});

test('adding nullable tsvector column', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'test');
    $blueprint->tsvector('search_vector')->nullable();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "test" add column "search_vector" tsvector null', $statements[0]);
});

test('adding tsvector column with stored as', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'test');
    $blueprint->tsvector('search_vector')->nullable()->storedAs("to_tsvector('english', coalesce(name, ''))");
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "test" add column "search_vector" tsvector null generated always as (to_tsvector(\'english\', coalesce(name, \'\'))) stored', $statements[0]);
});

test('create table with auto increment starting value', function () {
    $connection = postgresSchemaGrammarConnection();
    $connection->getSchemaBuilder()->shouldReceive('parseSchemaAndTable')->andReturn([null, 'users']);

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->create();
    $blueprint->increments('id')->startingValue(1000);
    $blueprint->string('email');
    $blueprint->string('name')->collation('nb_NO.utf8');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create table "users" ("id" serial not null primary key, "email" varchar(255) not null, "name" varchar(255) collate "nb_NO.utf8" not null)', $statements[0]);
    $this->assertSame("select setval(pg_get_serial_sequence('\"users\"', 'id'), 1000, false)", $statements[1]);
});

test('add columns with multiple auto increment starting value', function () {
    $builder = postgresSchemaGrammarBuilder();
    $builder->shouldReceive('parseSchemaAndTable')->andReturn([null, 'users']);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(builder: $builder), 'users');
    $blueprint->id()->from(100);
    $blueprint->increments('code')->from(200);
    $blueprint->string('name')->from(300);
    $statements = $blueprint->toSql();

    $this->assertEquals([
        'alter table "users" add column "id" bigserial not null primary key',
        'alter table "users" add column "code" serial not null primary key',
        'alter table "users" add column "name" varchar(255) not null',
        "select setval(pg_get_serial_sequence('\"users\"', 'id'), 100, false)",
        "select setval(pg_get_serial_sequence('\"users\"', 'code'), 200, false)",
    ], $statements);
});

test('create table and comment column', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->increments('id');
    $blueprint->string('email')->comment('my first comment');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create table "users" ("id" serial not null primary key, "email" varchar(255) not null)', $statements[0]);
    $this->assertSame('comment on column "users"."email" is \'my first comment\'', $statements[1]);
});

test('create temporary table', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->create();
    $blueprint->temporary();
    $blueprint->increments('id');
    $blueprint->string('email');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create temporary table "users" ("id" serial not null primary key, "email" varchar(255) not null)', $statements[0]);
});

test('drop table', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->drop();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table "users"', $statements[0]);
});

test('drop table if exists', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropIfExists();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop table if exists "users"', $statements[0]);
});

test('drop column', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropColumn('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop column "foo"', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropColumn(['foo', 'bar']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop column "foo", drop column "bar"', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropColumn('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop column "foo", drop column "bar"', $statements[0]);
});

test('drop primary', function () {
    $connection = postgresSchemaGrammarConnection();
    $connection->getSchemaBuilder()->shouldReceive('parseSchemaAndTable')->andReturn([null, 'users']);

    $blueprint = new Blueprint($connection, 'users');
    $blueprint->dropPrimary();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop constraint "users_pkey"', $statements[0]);
});

test('drop unique', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropUnique('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop constraint "foo"', $statements[0]);
});

test('drop index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropIndex('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "foo"', $statements[0]);
});

test('drop spatial index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->dropSpatialIndex(['coordinates']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('drop index "geo_coordinates_spatialindex"', $statements[0]);
});

test('drop foreign', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropForeign('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop constraint "foo"', $statements[0]);
});

test('drop timestamps', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropTimestamps();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop column "created_at", drop column "updated_at"', $statements[0]);
});

test('drop timestamps tz', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->dropTimestampsTz();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" drop column "created_at", drop column "updated_at"', $statements[0]);
});

test('drop morphs', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'photos');
    $blueprint->dropMorphs('imageable');
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('drop index "photos_imageable_type_imageable_id_index"', $statements[0]);
    $this->assertSame('alter table "photos" drop column "imageable_type", drop column "imageable_id"', $statements[1]);
});

test('rename table', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->rename('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" rename to "foo"', $statements[0]);
});

test('rename index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->renameIndex('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter index "foo" rename to "bar"', $statements[0]);
});

test('adding primary key', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->primary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add primary key ("foo")', $statements[0]);
});

test('adding unique key', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "bar" unique ("foo")', $statements[0]);
});

test('adding unique key with nulls not distinct', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar')->nullsNotDistinct();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "bar" unique nulls not distinct ("foo")', $statements[0]);
});

test('adding unique key with nulls distinct', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->unique('foo', 'bar')->nullsNotDistinct(false);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "bar" unique nulls distinct ("foo")', $statements[0]);
});

test('adding unique key online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->unique('foo')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create unique index concurrently "users_foo_unique" on "users" ("foo")', $statements[0]);
    $this->assertSame('alter table "users" add constraint "users_foo_unique" unique using index "users_foo_unique"', $statements[1]);
});

test('adding index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "baz" on "users" ("foo", "bar")', $statements[0]);
});

test('adding index with algorithm', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->index(['foo', 'bar'], 'baz', 'hash');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "baz" on "users" using hash ("foo", "bar")', $statements[0]);
});

test('adding index online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->index('foo', 'baz')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "baz" on "users" ("foo")', $statements[0]);
});

test('adding fulltext index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->fulltext('body');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'english\', "body")))', $statements[0]);
});

test('adding fulltext index multiple columns', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->fulltext(['body', 'title']);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "users_body_title_fulltext" on "users" using gin ((to_tsvector(\'english\', "body") || to_tsvector(\'english\', "title")))', $statements[0]);
});

test('adding fulltext index with language', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->fulltext('body')->language('spanish');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'spanish\', "body")))', $statements[0]);
});

test('adding fulltext index online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->fulltext('body')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "users_body_fulltext" on "users" using gin ((to_tsvector(\'english\', "body")))', $statements[0]);
});

test('adding fulltext index with fluency', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('body')->fulltext();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'english\', "body")))', $statements[1]);
});

test('adding spatial index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "geo_coordinates_spatialindex" on "geo" using gist ("coordinates")', $statements[0]);
});

test('adding spatial index online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "geo_coordinates_spatialindex" on "geo" using gist ("coordinates")', $statements[0]);
});

test('adding fluent spatial index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point')->spatialIndex();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create index "geo_coordinates_spatialindex" on "geo" using gist ("coordinates")', $statements[1]);
});

test('adding spatial index with operator class', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates', 'my_index', 'point_ops');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "my_index" on "geo" using gist ("coordinates" point_ops)', $statements[0]);
});

test('adding spatial index with operator class multiple columns', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->spatialIndex(['coordinates', 'location'], 'my_index', 'point_ops');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "my_index" on "geo" using gist ("coordinates" point_ops, "location" point_ops)', $statements[0]);
});

test('adding spatial index with operator class online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->spatialIndex('coordinates', 'my_index', 'point_ops')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "my_index" on "geo" using gist ("coordinates" point_ops)', $statements[0]);
});

test('adding vector index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'posts');
    $blueprint->vectorIndex('embeddings');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "posts_embeddings_vectorindex" on "posts" using hnsw ("embeddings" vector_cosine_ops)', $statements[0]);
});

test('adding vector index online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'posts');
    $blueprint->vectorIndex('embeddings')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "posts_embeddings_vectorindex" on "posts" using hnsw ("embeddings" vector_cosine_ops)', $statements[0]);
});

test('adding vector index with name', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'posts');
    $blueprint->vectorIndex('embeddings', 'my_vector_index');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "my_vector_index" on "posts" using hnsw ("embeddings" vector_cosine_ops)', $statements[0]);
});

test('adding fluent vector index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'posts');
    $blueprint->vector('embeddings', 1536)->vectorIndex();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create index "posts_embeddings_vectorindex" on "posts" using hnsw ("embeddings" vector_cosine_ops)', $statements[1]);
});

test('adding fluent index on vector column', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'posts');
    $blueprint->vector('embeddings', 1536)->index();
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('create index "posts_embeddings_vectorindex" on "posts" using hnsw ("embeddings" vector_cosine_ops)', $statements[1]);
});

test('adding raw index', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->rawIndex('(function(column))', 'raw_index');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index "raw_index" on "users" ((function(column)))', $statements[0]);
});

test('adding raw index online', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->rawIndex('(function(column))', 'raw_index')->online();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('create index concurrently "raw_index" on "users" ((function(column)))', $statements[0]);
});

test('adding incrementing id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->increments('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" serial not null primary key', $statements[0]);
});

test('adding small incrementing id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->smallIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" smallserial not null primary key', $statements[0]);
});

test('adding medium incrementing id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->mediumIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" serial not null primary key', $statements[0]);
});

test('adding id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->id();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" bigserial not null primary key', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->id('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" bigserial not null primary key', $statements[0]);
});

test('adding foreign id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $foreignId = $blueprint->foreignId('foo');
    $blueprint->foreignId('company_id')->constrained();
    $blueprint->foreignId('laravel_idea_id')->constrained();
    $blueprint->foreignId('team_id')->references('id')->on('teams');
    $blueprint->foreignId('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignId);
    $this->assertSame([
        'alter table "users" add column "foo" bigint not null',
        'alter table "users" add column "company_id" bigint not null',
        'alter table "users" add constraint "users_company_id_foreign" foreign key ("company_id") references "companies" ("id")',
        'alter table "users" add column "laravel_idea_id" bigint not null',
        'alter table "users" add constraint "users_laravel_idea_id_foreign" foreign key ("laravel_idea_id") references "laravel_ideas" ("id")',
        'alter table "users" add column "team_id" bigint not null',
        'alter table "users" add constraint "users_team_id_foreign" foreign key ("team_id") references "teams" ("id")',
        'alter table "users" add column "team_column_id" bigint not null',
        'alter table "users" add constraint "users_team_column_id_foreign" foreign key ("team_column_id") references "teams" ("id")',
    ], $statements);
});

test('adding foreign id specifying index name in constraint', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->foreignId('company_id')->constrained(indexName: 'my_index');
    $statements = $blueprint->toSql();
    $this->assertSame([
        'alter table "users" add column "company_id" bigint not null',
        'alter table "users" add constraint "my_index" foreign key ("company_id") references "companies" ("id")',
    ], $statements);
});

test('adding big incrementing id', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->bigIncrements('id');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "id" bigserial not null primary key', $statements[0]);
});

test('adding string', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar(255) not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('foo', 100);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar(100) not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('foo', 100)->nullable()->default('bar');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar(100) null default \'bar\'', $statements[0]);
});

test('adding string without length limit', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" varchar(255) not null', $statements[0]);

    Builder::$defaultStringLength = null;

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->string('foo');
    $statements = $blueprint->toSql();

    try {
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
    } finally {
        Builder::$defaultStringLength = 255;
    }
});

test('adding char without length limit', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->char('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" char(255) not null', $statements[0]);

    Builder::$defaultStringLength = null;

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->char('foo');
    $statements = $blueprint->toSql();

    try {
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" char not null', $statements[0]);
    } finally {
        Builder::$defaultStringLength = 255;
    }
});

test('adding text', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->text('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
});

test('adding big integer', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->bigInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" bigint not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->bigInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" bigserial not null primary key', $statements[0]);
});

test('adding integer', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" serial not null primary key', $statements[0]);
});

test('adding medium integer', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->mediumInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" serial not null primary key', $statements[0]);
});

test('adding tiny integer', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" smallint not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->tinyInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" smallserial not null primary key', $statements[0]);
});

test('adding small integer', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->smallInteger('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" smallint not null', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->smallInteger('foo', true);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" smallserial not null primary key', $statements[0]);
});

test('adding float', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->float('foo', 5);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" float(5) not null', $statements[0]);
});

test('adding double', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->double('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" double precision not null', $statements[0]);
});

test('adding decimal', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->decimal('foo', 5, 2);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" decimal(5, 2) not null', $statements[0]);
});

test('adding boolean', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->boolean('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" boolean not null', $statements[0]);
});

test('adding enum', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->enum('role', ['member', 'admin']);
    $blueprint->enum('status', Foo::cases());
    $statements = $blueprint->toSql();

    $this->assertCount(2, $statements);
    $this->assertSame('alter table "users" add column "role" varchar(255) check ("role" in (\'member\', \'admin\')) not null', $statements[0]);
    $this->assertSame('alter table "users" add column "status" varchar(255) check ("status" in (\'bar\')) not null', $statements[1]);
});

test('adding date', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->date('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" date not null', $statements[0]);
});

test('adding date with default current', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->date('foo')->useCurrent();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" date not null default CURRENT_DATE', $statements[0]);
});

test('adding year', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->year('birth_year');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "birth_year" integer not null', $statements[0]);
});

test('adding year with default current', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->year('birth_year')->useCurrent();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "birth_year" integer not null default EXTRACT(YEAR FROM CURRENT_DATE)', $statements[0]);
});

test('adding json', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->json('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" json not null', $statements[0]);
});

test('adding jsonb', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->jsonb('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" jsonb not null', $statements[0]);
});

test('adding datetime methods', function (string $method, string $type, ?int $userPrecision, false|int|null $grammarPrecision, ?int $expected) {
    PostgresBuilder::defaultTimePrecision($grammarPrecision);
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->{$method}('created_at', $userPrecision);
    $statements = $blueprint->toSql();
    $type = is_null($expected) ? $type : "{$type}({$expected})";
    $with = str_contains($method, 'Tz') ? 'with' : 'without';
    $this->assertCount(1, $statements);
    $this->assertSame("alter table \"users\" add column \"created_at\" {$type} {$with} time zone not null", $statements[0]);
})->with(fn () => postgresSchemaGrammarDatetimeAndPrecisionProvider());

test('adding timestamps', function (string $method) {
    PostgresBuilder::defaultTimePrecision(0);
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->{$method}();
    $statements = $blueprint->toSql();
    $with = str_contains($method, 'Tz') ? 'with' : 'without';
    $this->assertCount(2, $statements);
    $this->assertSame([
        "alter table \"users\" add column \"created_at\" timestamp(0) {$with} time zone null",
        "alter table \"users\" add column \"updated_at\" timestamp(0) {$with} time zone null",
    ], $statements);
})->with(['timestamps', 'timestampsTz']);

test('adding binary', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->binary('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" bytea not null', $statements[0]);
});

test('adding uuid', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->uuid('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" uuid not null', $statements[0]);
});

test('adding uuid defaults column name', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->uuid();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "uuid" uuid not null', $statements[0]);
});

test('adding foreign uuid', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $foreignUuid = $blueprint->foreignUuid('foo');
    $blueprint->foreignUuid('company_id')->constrained();
    $blueprint->foreignUuid('laravel_idea_id')->constrained();
    $blueprint->foreignUuid('team_id')->references('id')->on('teams');
    $blueprint->foreignUuid('team_column_id')->constrained('teams');

    $statements = $blueprint->toSql();

    $this->assertInstanceOf(ForeignIdColumnDefinition::class, $foreignUuid);
    $this->assertSame([
        'alter table "users" add column "foo" uuid not null',
        'alter table "users" add column "company_id" uuid not null',
        'alter table "users" add constraint "users_company_id_foreign" foreign key ("company_id") references "companies" ("id")',
        'alter table "users" add column "laravel_idea_id" uuid not null',
        'alter table "users" add constraint "users_laravel_idea_id_foreign" foreign key ("laravel_idea_id") references "laravel_ideas" ("id")',
        'alter table "users" add column "team_id" uuid not null',
        'alter table "users" add constraint "users_team_id_foreign" foreign key ("team_id") references "teams" ("id")',
        'alter table "users" add column "team_column_id" uuid not null',
        'alter table "users" add constraint "users_team_column_id_foreign" foreign key ("team_column_id") references "teams" ("id")',
    ], $statements);
});

test('adding generated as', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->increments('foo')->generatedAs();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity primary key', $statements[0]);
    // With always modifier
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->increments('foo')->generatedAs()->always();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null generated always as identity primary key', $statements[0]);
    // With sequence options
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->increments('foo')->generatedAs('increment by 10 start with 100');
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity (increment by 10 start with 100) primary key', $statements[0]);
    // Not a primary key
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo')->generatedAs();
    $statements = $blueprint->toSql();
    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity', $statements[0]);
});

test('adding virtual as', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo')->nullable();
    $blueprint->boolean('bar')->virtualAs('foo is not null');
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add column "foo" integer null',
        'alter table "users" add column "bar" boolean not null generated always as (foo is not null) virtual',
    ], $statements);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo')->nullable();
    $blueprint->boolean('bar')->virtualAs(new Expression('foo is not null'));
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add column "foo" integer null',
        'alter table "users" add column "bar" boolean not null generated always as (foo is not null) virtual',
    ], $statements);
});

test('adding stored as', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo')->nullable();
    $blueprint->boolean('bar')->storedAs('foo is not null');
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add column "foo" integer null',
        'alter table "users" add column "bar" boolean not null generated always as (foo is not null) stored',
    ], $statements);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->integer('foo')->nullable();
    $blueprint->boolean('bar')->storedAs(new Expression('foo is not null'));
    $statements = $blueprint->toSql();
    $this->assertCount(2, $statements);
    $this->assertSame([
        'alter table "users" add column "foo" integer null',
        'alter table "users" add column "bar" boolean not null generated always as (foo is not null) stored',
    ], $statements);
});

test('adding ip address', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->ipAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" inet not null', $statements[0]);
});

test('adding ip address defaults column name', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->ipAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "ip_address" inet not null', $statements[0]);
});

test('adding mac address', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->macAddress('foo');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "foo" macaddr not null', $statements[0]);
});

test('adding mac address defaults column name', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->macAddress();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add column "mac_address" macaddr not null', $statements[0]);
});

test('compile foreign', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade')->deferrable();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "users_parent_id_foreign" foreign key ("parent_id") references "parents" ("id") on delete cascade deferrable', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade')->deferrable(false)->initiallyImmediate();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "users_parent_id_foreign" foreign key ("parent_id") references "parents" ("id") on delete cascade not deferrable', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade')->deferrable()->initiallyImmediate(false);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "users_parent_id_foreign" foreign key ("parent_id") references "parents" ("id") on delete cascade deferrable initially deferred', $statements[0]);

    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'users');
    $blueprint->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade')->deferrable()->notValid();
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "users" add constraint "users_parent_id_foreign" foreign key ("parent_id") references "parents" ("id") on delete cascade deferrable not valid', $statements[0]);
});

test('adding geometry', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
});

test('adding geography', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geography('coordinates', 'pointzm', 4269);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geography(pointzm,4269) not null', $statements[0]);
});

test('adding point', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(point) not null', $statements[0]);
});

test('adding point with srid', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'point', 4269);
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(point,4269) not null', $statements[0]);
});

test('adding line string', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'linestring');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(linestring) not null', $statements[0]);
});

test('adding polygon', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'polygon');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(polygon) not null', $statements[0]);
});

test('adding geometry collection', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'geometrycollection');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(geometrycollection) not null', $statements[0]);
});

test('adding multi point', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multipoint');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(multipoint) not null', $statements[0]);
});

test('adding multi line string', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multilinestring');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(multilinestring) not null', $statements[0]);
});

test('adding multi polygon', function () {
    $blueprint = new Blueprint(postgresSchemaGrammarConnection(), 'geo');
    $blueprint->geometry('coordinates', 'multipolygon');
    $statements = $blueprint->toSql();

    $this->assertCount(1, $statements);
    $this->assertSame('alter table "geo" add column "coordinates" geometry(multipolygon) not null', $statements[0]);
});

test('create database', function () {
    $connection = postgresSchemaGrammarConnection();
    $connection->shouldReceive('getConfig')->once()->once()->with('charset')->andReturn('utf8_foo');
    $statement = postgresSchemaGrammarGrammar($connection)->compileCreateDatabase('my_database_a');

    $this->assertSame(
        'create database "my_database_a" encoding "utf8_foo"',
        $statement
    );

    $connection = postgresSchemaGrammarConnection();
    $connection->shouldReceive('getConfig')->once()->once()->with('charset')->andReturn('utf8_bar');
    $statement = postgresSchemaGrammarGrammar($connection)->compileCreateDatabase('my_database_b');

    $this->assertSame(
        'create database "my_database_b" encoding "utf8_bar"',
        $statement
    );
});

test('drop database if exists', function () {
    $statement = postgresSchemaGrammarGrammar()->compileDropDatabaseIfExists('my_database_a');

    $this->assertSame(
        'drop database if exists "my_database_a"',
        $statement
    );

    $statement = postgresSchemaGrammarGrammar()->compileDropDatabaseIfExists('my_database_b');

    $this->assertSame(
        'drop database if exists "my_database_b"',
        $statement
    );
});

test('drop all tables escapes table names', function () {
    $statement = postgresSchemaGrammarGrammar()->compileDropAllTables(['alpha', 'beta', 'gamma']);

    $this->assertSame('drop table "alpha", "beta", "gamma" cascade', $statement);
});

test('drop all views escapes table names', function () {
    $statement = postgresSchemaGrammarGrammar()->compileDropAllViews(['alpha', 'beta', 'gamma']);

    $this->assertSame('drop view "alpha", "beta", "gamma" cascade', $statement);
});

test('drop all types escapes table names', function () {
    $statement = postgresSchemaGrammarGrammar()->compileDropAllTypes(['alpha', 'beta', 'gamma']);

    $this->assertSame('drop type "alpha", "beta", "gamma" cascade', $statement);
});

test('drop all tables with prefix and schema', function () {
    $connection = postgresSchemaGrammarConnection(prefix: 'prefix_');
    $statement = postgresSchemaGrammarGrammar($connection)->compileDropAllTables(['schema.alpha', 'schema.beta', 'schema.gamma']);

    $this->assertSame('drop table "schema"."alpha", "schema"."beta", "schema"."gamma" cascade', $statement);
});

test('drop all views with prefix and schema', function () {
    $connection = postgresSchemaGrammarConnection(prefix: 'prefix_');
    $statement = postgresSchemaGrammarGrammar($connection)->compileDropAllViews(['schema.alpha', 'schema.beta', 'schema.gamma']);

    $this->assertSame('drop view "schema"."alpha", "schema"."beta", "schema"."gamma" cascade', $statement);
});

test('drop all types with prefix and schema', function () {
    $connection = postgresSchemaGrammarConnection(prefix: 'prefix_');
    $statement = postgresSchemaGrammarGrammar($connection)->compileDropAllTypes(['schema.alpha', 'schema.beta', 'schema.gamma']);

    $this->assertSame('drop type "schema"."alpha", "schema"."beta", "schema"."gamma" cascade', $statement);
});

test('drop all domains with prefix and schema', function () {
    $connection = postgresSchemaGrammarConnection(prefix: 'prefix_');
    $statement = postgresSchemaGrammarGrammar($connection)->compileDropAllDomains(['schema.alpha', 'schema.beta', 'schema.gamma']);

    $this->assertSame('drop domain "schema"."alpha", "schema"."beta", "schema"."gamma" cascade', $statement);
});

test('compile columns', function () {
    $connection = postgresSchemaGrammarConnection();
    $connection->shouldReceive('getServerVersion')->once()->andReturn('12.0.0');

    $statement = $connection->getSchemaGrammar()->compileColumns('public', 'table');

    $this->assertStringContainsString("where c.relname = 'table' and n.nspname = 'public'", $statement);
    $this->assertStringContainsString('pg_catalog.pg_collation', $statement);
    $this->assertStringContainsString('a.attgenerated as generated', $statement);
});

test('compile columns on legacy server', function () {
    $connection = postgresSchemaGrammarConnection();
    $connection->shouldReceive('getServerVersion')->once()->andReturn('8.0.2');

    $statement = $connection->getSchemaGrammar()->compileColumns('public', 'table');

    $this->assertStringContainsString("where c.relname = 'table' and n.nspname = 'public'", $statement);
    $this->assertStringContainsString('null as collation', $statement);
    $this->assertStringContainsString("'' as generated", $statement);
    $this->assertStringNotContainsString('pg_catalog.pg_collation', $statement);
    $this->assertStringNotContainsString('a.attgenerated', $statement);
});

test('grammars are macroable', function () {
    // compileReplace macro.
    postgresSchemaGrammarGrammar()::macro('compileReplace', function () {
        return true;
    });

    $c = postgresSchemaGrammarGrammar()::compileReplace();

    $this->assertTrue($c);
});

function postgresSchemaGrammarConnection(
    ?PostgresGrammar $grammar = null,
    ?PostgresBuilder $builder = null,
    string $prefix = ''
) {
    $connection = m::mock(Connection::class)
        ->shouldReceive('getTablePrefix')->andReturn($prefix)
        ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
        ->getMock();

    $grammar ??= postgresSchemaGrammarGrammar($connection);
    $builder ??= postgresSchemaGrammarBuilder();

    return $connection
        ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
        ->shouldReceive('getSchemaBuilder')->andReturn($builder)
        ->getMock();
}

function postgresSchemaGrammarGrammar(?Connection $connection = null)
{
    return new PostgresGrammar($connection ?? postgresSchemaGrammarConnection());
}

function postgresSchemaGrammarBuilder()
{
    return mock(PostgresBuilder::class);
}

/** @return list<array{method: string, type: string, user: int|null, grammar: false|int|null, expected: int|null}> */
function postgresSchemaGrammarDatetimeAndPrecisionProvider(): array
{
    $methods = [
        ['method' => 'datetime', 'type' => 'timestamp'],
        ['method' => 'datetimeTz', 'type' => 'timestamp'],
        ['method' => 'timestamp', 'type' => 'timestamp'],
        ['method' => 'timestampTz', 'type' => 'timestamp'],
        ['method' => 'time', 'type' => 'time'],
        ['method' => 'timeTz', 'type' => 'time'],
    ];
    $precisions = [
        'user can override grammar default' => ['userPrecision' => 1, 'grammarPrecision' => null, 'expected' => 1],
        'fallback to grammar default' => ['userPrecision' => null, 'grammarPrecision' => 5, 'expected' => 5],
        'fallback to database default' => ['userPrecision' => null, 'grammarPrecision' => null, 'expected' => null],
    ];

    $result = [];

    foreach ($methods as $datetime) {
        foreach ($precisions as $precision) {
            $result[] = array_merge($datetime, $precision);
        }
    }

    return $result;
}
